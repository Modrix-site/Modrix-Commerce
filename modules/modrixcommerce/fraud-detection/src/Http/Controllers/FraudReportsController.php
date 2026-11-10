<?php

namespace EcoModular\FraudDetection\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\FraudDetection\Services\FraudDashboardService;
use EcoModular\FraudDetection\Services\FraudReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FraudReportsController extends Controller
{
    public function __construct(
        private readonly FraudDashboardService $dashboard,
        private readonly FraudReportService $reportService,
    ) {}

    public function index(Request $request): Response
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        return Inertia::render('module/modrixcommerce/fraud-detection/admin/reports', [
            'stats' => $this->dashboard->getStats(),
            'riskTrends' => $this->dashboard->getRiskTrends(30),
            'topIndicators' => $this->dashboard->getTopIndicators(10),
            'riskDistribution' => $this->dashboard->getRiskDistribution(),
            'dailySummary' => $this->reportService->getDailyBreakdown(14),
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
            'exportCsvUrl' => route('admin.modrixcommerce.fraud-detection.reports.export-csv', array_filter(['from' => $from, 'to' => $to])),
            'exportExcelUrl' => route('admin.modrixcommerce.fraud-detection.reports.export-excel', array_filter(['from' => $from, 'to' => $to])),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        return $this->reportService->exportCsv(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        return $this->reportService->exportExcel(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );
    }
}
