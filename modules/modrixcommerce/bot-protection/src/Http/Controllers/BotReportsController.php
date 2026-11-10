<?php

namespace EcoModular\BotProtection\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\BotProtection\Services\BotReportService;
use EcoModular\BotProtection\Services\TrafficMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BotReportsController extends Controller
{
    public function __construct(
        private readonly BotReportService $reportService,
        private readonly TrafficMonitoringService $monitoring,
    ) {}

    public function index(Request $request): Response
    {
        $days = (int) $request->query('days', 14);
        $dailySummary = $this->reportService->getDailyBreakdown($days);
        $stats = $this->monitoring->getDashboardStats();

        return Inertia::render('module/modrixcommerce/bot-protection/admin/reports', [
            'stats' => $stats,
            'dailySummary' => $dailySummary,
            'threatDistribution' => $this->monitoring->getThreatDistribution(),
            'topAttackingIps' => $this->monitoring->getTopAttackingIps(5),
            'selectedDays' => $days,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $days = (int) $request->query('days', 30);
        return $this->reportService->exportCsv($days);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $days = (int) $request->query('days', 30);
        return $this->reportService->exportExcel($days);
    }
}
