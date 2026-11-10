<?php

namespace EcoModular\BotProtection\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\BotProtection\Services\TrafficMonitoringService;
use Inertia\Inertia;
use Inertia\Response;

class BotDashboardController extends Controller
{
    public function __construct(
        private readonly TrafficMonitoringService $monitoring,
    ) {}

    public function index(): Response
    {
        return Inertia::render('module/modrixcommerce/bot-protection/admin/dashboard', [
            'stats' => $this->monitoring->getDashboardStats(),
            'hourlyTrends' => $this->monitoring->getHourlyThreatTrends(),
            'topAttackingIps' => $this->monitoring->getTopAttackingIps(8),
            'topTargetedPages' => $this->monitoring->getTopTargetedPages(8),
            'threatDistribution' => $this->monitoring->getThreatDistribution(),
            'recentIncidents' => $this->monitoring->getRecentIncidents(10),
        ]);
    }
}
