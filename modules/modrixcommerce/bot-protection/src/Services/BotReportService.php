<?php

namespace EcoModular\BotProtection\Services;

use EcoModular\BotProtection\Models\BotAttackReport;
use EcoModular\BotProtection\Models\BotEvent;
use EcoModular\BotProtection\Models\BotTrafficLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BotReportService
{
    public function getDailyBreakdown(int $days = 14): array
    {
        $breakdown = [];
        $endDate = Carbon::today();

        for ($i = 0; $i < $days; $i++) {
            $date = $endDate->copy()->subDays($i);
            $dateStr = $date->toDateString();

            $existing = BotAttackReport::query()->where('report_date', $dateStr)->first();

            if ($existing) {
                $breakdown[] = [
                    'date' => $date->format('M d, Y'),
                    'total_requests' => $existing->total_requests,
                    'human_requests' => $existing->human_requests,
                    'bot_requests' => $existing->bot_requests,
                    'blocked' => $existing->blocked_requests,
                    'challenged' => $existing->challenged_requests,
                    'scraping' => $existing->scraping_attempts,
                    'credential_stuffing' => $existing->credential_stuffing_attempts,
                    'checkout_abuse' => $existing->checkout_abuse_attempts,
                    'avg_score' => (float) $existing->avg_bot_score,
                ];
            } else {
                // Calculate from logs
                $start = $date->copy()->startOfDay();
                $end = $date->copy()->endOfDay();

                $total = BotTrafficLog::query()->whereBetween('created_at', [$start, $end])->count();
                $bots = BotTrafficLog::query()->whereBetween('created_at', [$start, $end])->where('is_bot', true)->count();
                $blocked = BotTrafficLog::query()->whereBetween('created_at', [$start, $end])->where('action', 'block')->count();
                $challenged = BotTrafficLog::query()->whereBetween('created_at', [$start, $end])->where('action', 'challenge')->count();

                $scraping = BotEvent::query()->whereBetween('created_at', [$start, $end])->where('bot_type', 'scraper')->count();
                $cred = BotEvent::query()->whereBetween('created_at', [$start, $end])->where('bot_type', 'credential_stuffing')->count();
                $checkout = BotEvent::query()->whereBetween('created_at', [$start, $end])->where('bot_type', 'checkout_bot')->count();

                $avgScore = BotTrafficLog::query()->whereBetween('created_at', [$start, $end])->avg('bot_score') ?? 0;

                $breakdown[] = [
                    'date' => $date->format('M d, Y'),
                    'total_requests' => $total,
                    'human_requests' => max(0, $total - $bots),
                    'bot_requests' => $bots,
                    'blocked' => $blocked,
                    'challenged' => $challenged,
                    'scraping' => $scraping,
                    'credential_stuffing' => $cred,
                    'checkout_abuse' => $checkout,
                    'avg_score' => round((float) $avgScore, 1),
                ];
            }
        }

        return $breakdown;
    }

    public function generateReportForDate(\DateTimeInterface|string $date): BotAttackReport
    {
        $date = $date instanceof \DateTimeInterface ? Carbon::parse($date->format('Y-m-d')) : Carbon::parse($date);
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $total = BotTrafficLog::query()->whereBetween('created_at', [$start, $end])->count();
        $bots = BotTrafficLog::query()->whereBetween('created_at', [$start, $end])->where('is_bot', true)->count();
        $blocked = BotTrafficLog::query()->whereBetween('created_at', [$start, $end])->where('action', 'block')->count();
        $challenged = BotTrafficLog::query()->whereBetween('created_at', [$start, $end])->where('action', 'challenge')->count();

        $scraping = BotEvent::query()->whereBetween('created_at', [$start, $end])->where('bot_type', 'scraper')->count();
        $cred = BotEvent::query()->whereBetween('created_at', [$start, $end])->where('bot_type', 'credential_stuffing')->count();
        $checkout = BotEvent::query()->whereBetween('created_at', [$start, $end])->where('bot_type', 'checkout_bot')->count();

        $avgScore = BotTrafficLog::query()->whereBetween('created_at', [$start, $end])->avg('bot_score') ?? 0;

        $topThreats = BotEvent::query()
            ->select('bot_type', DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('bot_type')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'bot_type')
            ->toArray();

        $topIps = BotEvent::query()
            ->select('ip_address', DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('ip_address')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'ip_address')
            ->toArray();

        return BotAttackReport::updateOrCreate(
            ['report_date' => $date->toDateString()],
            [
                'total_requests' => $total,
                'human_requests' => max(0, $total - $bots),
                'bot_requests' => $bots,
                'blocked_requests' => $blocked,
                'challenged_requests' => $challenged,
                'solved_challenges' => 0,
                'failed_challenges' => 0,
                'scraping_attempts' => $scraping,
                'credential_stuffing_attempts' => $cred,
                'checkout_abuse_attempts' => $checkout,
                'avg_bot_score' => round((float) $avgScore, 2),
                'top_threat_types' => $topThreats,
                'top_attacking_ips' => $topIps,
            ]
        );
    }

    public function exportCsv(int $days = 30): StreamedResponse
    {
        $breakdown = $this->getDailyBreakdown($days);
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bot-protection-report-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($breakdown) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Date',
                'Total Requests',
                'Human Requests',
                'Bot Requests',
                'Blocked',
                'Challenged',
                'Scraping Attempts',
                'Credential Stuffing',
                'Checkout Abuse',
                'Avg Risk Score',
            ]);

            foreach ($breakdown as $row) {
                fputcsv($handle, [
                    $row['date'],
                    $row['total_requests'],
                    $row['human_requests'],
                    $row['bot_requests'],
                    $row['blocked'],
                    $row['challenged'],
                    $row['scraping'],
                    $row['credential_stuffing'],
                    $row['checkout_abuse'],
                    $row['avg_score'],
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function exportExcel(int $days = 30): StreamedResponse
    {
        // Deliver clean XML-compatible spreadsheet format for Excel
        $breakdown = $this->getDailyBreakdown($days);
        $filename = "bot-protection-report-" . now()->format('Y-m-d') . ".xls";

        return response()->stream(function () use ($breakdown) {
            echo "Date\tTotal Requests\tHuman Requests\tBot Requests\tBlocked\tChallenged\tScraping\tCredential Stuffing\tCheckout Abuse\tAvg Score\n";
            foreach ($breakdown as $row) {
                echo "{$row['date']}\t{$row['total_requests']}\t{$row['human_requests']}\t{$row['bot_requests']}\t{$row['blocked']}\t{$row['challenged']}\t{$row['scraping']}\t{$row['credential_stuffing']}\t{$row['checkout_abuse']}\t{$row['avg_score']}\n";
            }
        }, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
