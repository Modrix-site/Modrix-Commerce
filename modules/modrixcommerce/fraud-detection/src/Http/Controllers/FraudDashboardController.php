<?php

namespace EcoModular\FraudDetection\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\FraudDetection\Services\FraudDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class FraudDashboardController extends Controller
{
    public function __construct(
        private readonly FraudDashboardService $dashboard,
    ) {}

    public function index(): Response
    {
        return Inertia::render('module/modrixcommerce/fraud-detection/admin/dashboard', [
            'stats' => $this->dashboard->getStats(),
            'riskTrends' => $this->dashboard->getRiskTrends(30),
            'topIndicators' => $this->dashboard->getTopIndicators(8),
            'riskDistribution' => $this->dashboard->getRiskDistribution(),
            'recentHighRisk' => $this->dashboard->getRecentHighRisk(10),
        ]);
    }
}
