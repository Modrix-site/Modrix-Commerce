<?php

namespace EcoModular\FraudDetection\Services;

use Carbon\Carbon;
use EcoModular\FraudDetection\Models\FraudScore;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FraudReportService
{
    /**
     * @return array<string, mixed>
     */
    public function getDailySummary(?string $date = null): array
    {
        $date = $date ? Carbon::parse($date) : now();

        $scores = FraudScore::query()
            ->whereDate('created_at', $date)
            ->get();

        return [
            'date' => $date->toFormattedDateString(),
            'total_orders' => $scores->count(),
            'high_risk' => $scores->where('risk_level', 'high')->count(),
            'medium_risk' => $scores->where('risk_level', 'medium')->count(),
            'low_risk' => $scores->where('risk_level', 'low')->count(),
            'approved' => $scores->where('review_status', 'approved')->count(),
            'rejected' => $scores->where('review_status', 'rejected')->count(),
            'pending' => $scores->where('review_status', 'pending')->count(),
            'avg_score' => round($scores->avg('total_score') ?? 0, 1),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDailyBreakdown(int $days = 14): array
    {
        $breakdown = [];
        for ($i = 0; $i < $days; $i++) {
            $d = now()->subDays($i);
            $scores = FraudScore::query()
                ->whereDate('created_at', $d)
                ->get();

            $breakdown[] = [
                'date' => $d->format('M d, Y'),
                'total_orders' => $scores->count(),
                'high_risk' => $scores->where('risk_level', 'high')->count(),
                'medium_risk' => $scores->where('risk_level', 'medium')->count(),
                'low_risk' => $scores->where('risk_level', 'low')->count(),
                'approved' => $scores->where('review_status', 'approved')->count(),
                'rejected' => $scores->where('review_status', 'rejected')->count(),
                'pending' => $scores->where('review_status', 'pending')->count(),
                'avg_score' => round($scores->avg('total_score') ?? 0, 1),
            ];
        }

        return $breakdown;
    }

    /**
     * Export fraud scores as a CSV streamed response.
     */
    public function exportCsv(?string $from = null, ?string $to = null): StreamedResponse
    {
        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'ID', 'Order ID', 'Customer Email', 'Risk Score', 'Risk Level',
                'Review Status', 'IP Address', 'Customer Country', 'Payment Country',
                'VPN/Proxy', 'Disposable Email', 'Address Mismatch', 'Failed Payments',
                'Order Total', 'Currency', 'Indicators', 'Reviewed By', 'Reviewed At', 'Created At',
            ], escape: '');

            $query = FraudScore::query()->with('reviewer')->orderBy('id');

            if ($from) {
                $query->where('created_at', '>=', $from);
            }
            if ($to) {
                $query->where('created_at', '<=', $to.' 23:59:59');
            }

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $score) {
                    /** @var FraudScore $score */
                    $indicators = is_array($score->indicators)
                        ? implode('; ', array_map(fn ($i) => ($i['label'] ?? '').' (+'.($i['score'] ?? 0).')', $score->indicators))
                        : '';

                    fputcsv($out, array_map(self::safe(...), [
                        (string) $score->id,
                        (string) $score->order_id,
                        (string) ($score->customer_email ?? ''),
                        (string) $score->total_score,
                        $score->risk_level,
                        $score->review_status,
                        (string) ($score->ip_address ?? ''),
                        (string) ($score->customer_country ?? ''),
                        (string) ($score->payment_country ?? ''),
                        $score->is_vpn || $score->is_proxy ? 'Yes' : 'No',
                        $score->is_disposable_email ? 'Yes' : 'No',
                        $score->address_mismatch ? 'Yes' : 'No',
                        (string) $score->failed_payment_attempts,
                        (string) $score->order_total,
                        $score->currency,
                        $indicators,
                        $score->reviewer?->name ?? '',
                        $score->reviewed_at?->toIso8601String() ?? '',
                        $score->created_at?->toIso8601String() ?? '',
                    ]), escape: '');
                }
            });

            fclose($out);
        }, 'fraud-report-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export fraud scores as an Excel-compatible CSV (with BOM for proper encoding).
     */
    public function exportExcel(?string $from = null, ?string $to = null): StreamedResponse
    {
        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // BOM for Excel UTF-8 compatibility
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID', 'Order ID', 'Customer Email', 'Risk Score', 'Risk Level',
                'Review Status', 'IP Address', 'Customer Country', 'Payment Country',
                'VPN/Proxy', 'Disposable Email', 'Address Mismatch', 'Failed Payments',
                'Order Total', 'Currency', 'Indicators', 'Reviewed By', 'Reviewed At', 'Created At',
            ], separator: "\t", escape: '');

            $query = FraudScore::query()->with('reviewer')->orderBy('id');

            if ($from) {
                $query->where('created_at', '>=', $from);
            }
            if ($to) {
                $query->where('created_at', '<=', $to.' 23:59:59');
            }

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $score) {
                    /** @var FraudScore $score */
                    $indicators = is_array($score->indicators)
                        ? implode('; ', array_map(fn ($i) => ($i['label'] ?? '').' (+'.($i['score'] ?? 0).')', $score->indicators))
                        : '';

                    fputcsv($out, array_map(self::safe(...), [
                        (string) $score->id,
                        (string) $score->order_id,
                        (string) ($score->customer_email ?? ''),
                        (string) $score->total_score,
                        $score->risk_level,
                        $score->review_status,
                        (string) ($score->ip_address ?? ''),
                        (string) ($score->customer_country ?? ''),
                        (string) ($score->payment_country ?? ''),
                        $score->is_vpn || $score->is_proxy ? 'Yes' : 'No',
                        $score->is_disposable_email ? 'Yes' : 'No',
                        $score->address_mismatch ? 'Yes' : 'No',
                        (string) $score->failed_payment_attempts,
                        (string) $score->order_total,
                        $score->currency,
                        $indicators,
                        $score->reviewer?->name ?? '',
                        $score->reviewed_at?->toIso8601String() ?? '',
                        $score->created_at?->toIso8601String() ?? '',
                    ]), separator: "\t", escape: '');
                }
            });

            fclose($out);
        }, 'fraud-report-'.now()->format('Y-m-d').'.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
