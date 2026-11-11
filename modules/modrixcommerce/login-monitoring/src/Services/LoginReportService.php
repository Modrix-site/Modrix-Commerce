<?php

namespace EcoModular\LoginMonitoring\Services;

use EcoModular\LoginMonitoring\Models\LoginEvent;
use EcoModular\LoginMonitoring\Models\LoginSecurityIncident;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoginReportService
{
    public function getDailyBreakdown(int $days = 14): array
    {
        $breakdown = [];
        $endDate = Carbon::today();

        for ($i = 0; $i < $days; $i++) {
            $date = $endDate->copy()->subDays($i);
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();

            $total = LoginEvent::query()->whereBetween('created_at', [$start, $end])->count();
            $success = LoginEvent::query()->whereBetween('created_at', [$start, $end])->where('status', 'success')->count();
            $failed = LoginEvent::query()->whereBetween('created_at', [$start, $end])->where('status', 'failed')->count();
            $suspicious = LoginEvent::query()->whereBetween('created_at', [$start, $end])->where('is_suspicious', true)->count();

            $incidents = LoginSecurityIncident::query()->whereBetween('created_at', [$start, $end])->count();
            $avgScore = LoginEvent::query()->whereBetween('created_at', [$start, $end])->avg('risk_score') ?? 0;

            $breakdown[] = [
                'date' => $date->format('M d, Y'),
                'total_attempts' => $total,
                'successful' => $success,
                'failed' => $failed,
                'suspicious' => $suspicious,
                'incidents' => $incidents,
                'avg_risk_score' => round((float) $avgScore, 1),
            ];
        }

        return $breakdown;
    }

    public function exportCsv(int $days = 30): StreamedResponse
    {
        $breakdown = $this->getDailyBreakdown($days);
        $filename = 'login-monitoring-report-'.now()->format('Y-m-d').'.csv';

        return response()->stream(function () use ($breakdown) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Total Attempts', 'Successful Logins', 'Failed Logins', 'Suspicious Logins', 'Security Incidents', 'Avg Risk Score']);
            foreach ($breakdown as $row) {
                fputcsv($handle, [
                    $row['date'],
                    $row['total_attempts'],
                    $row['successful'],
                    $row['failed'],
                    $row['suspicious'],
                    $row['incidents'],
                    $row['avg_risk_score'],
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportExcel(int $days = 30): StreamedResponse
    {
        $breakdown = $this->getDailyBreakdown($days);
        $filename = 'login-monitoring-report-'.now()->format('Y-m-d').'.xls';

        return response()->stream(function () use ($breakdown) {
            echo "Date\tTotal Attempts\tSuccessful Logins\tFailed Logins\tSuspicious Logins\tSecurity Incidents\tAvg Risk Score\n";
            foreach ($breakdown as $row) {
                echo "{$row['date']}\t{$row['total_attempts']}\t{$row['successful']}\t{$row['failed']}\t{$row['suspicious']}\t{$row['incidents']}\t{$row['avg_risk_score']}\n";
            }
        }, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
