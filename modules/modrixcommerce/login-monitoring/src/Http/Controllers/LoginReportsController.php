<?php

namespace EcoModular\LoginMonitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\LoginMonitoring\Models\LoginEvent;
use EcoModular\LoginMonitoring\Services\LoginReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoginReportsController extends Controller
{
    public function __construct(
        private readonly LoginReportService $reportService,
    ) {}

    public function index(Request $request): Response
    {
        $days = (int) $request->input('days', 14);
        if (! in_array($days, [7, 14, 30, 90])) {
            $days = 14;
        }

        $breakdown = $this->reportService->getDailyBreakdown($days);

        // Top failure reasons
        $failureReasons = LoginEvent::query()
            ->selectRaw("COALESCE(failure_reason, 'Unknown') as reason, COUNT(*) as count")
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->map(fn ($r) => [
                'reason' => $r->reason,
                'count' => (int) $r->count,
            ])
            ->values();

        // Top targeted emails
        $topTargetedEmails = LoginEvent::query()
            ->selectRaw("email, COUNT(*) as attempts, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('email')
            ->orderByDesc('failed_count')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'email' => $r->email,
                'attempts' => (int) $r->attempts,
                'failed_count' => (int) $r->failed_count,
            ])
            ->values();

        // Top IPs
        $topIps = LoginEvent::query()
            ->selectRaw("ip_address, COALESCE(country_code, 'Unknown') as country, COUNT(*) as count, MAX(risk_score) as max_score")
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('ip_address', 'country')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'ip_address' => $r->ip_address,
                'country' => $r->country,
                'count' => (int) $r->count,
                'max_score' => (int) $r->max_score,
            ])
            ->values();

        return Inertia::render('module/modrixcommerce/login-monitoring/admin/reports', [
            'days' => $days,
            'breakdown' => $breakdown,
            'failureReasons' => $failureReasons,
            'topTargetedEmails' => $topTargetedEmails,
            'topIps' => $topIps,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $days = (int) $request->input('days', 30);

        return $this->reportService->exportCsv($days);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $days = (int) $request->input('days', 30);

        return $this->reportService->exportExcel($days);
    }
}
