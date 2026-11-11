<?php

namespace EcoModular\BotProtection\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\BotProtection\Models\BotAuditLog;
use EcoModular\BotProtection\Models\BotEvent;
use EcoModular\BotProtection\Services\TrafficMonitoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class BotSecurityCenterController extends Controller
{
    public function __construct(
        private readonly TrafficMonitoringService $monitoring,
    ) {}

    public function index(Request $request): Response
    {
        $statusFilter = $request->query('status', 'all');
        $threatFilter = $request->query('threat', 'all');

        $query = BotEvent::query()->orderByDesc('created_at');

        if ($statusFilter === 'unresolved') {
            $query->where('is_resolved', false);
        } elseif ($statusFilter === 'resolved') {
            $query->where('is_resolved', true);
        }

        if ($threatFilter !== 'all') {
            $query->where('threat_level', $threatFilter);
        }

        $events = $query->paginate(15)->withQueryString();

        return Inertia::render('module/modrixcommerce/bot-protection/admin/security-center', [
            'stats' => $this->monitoring->getDashboardStats(),
            'events' => $events,
            'filters' => [
                'status' => $statusFilter,
                'threat' => $threatFilter,
            ],
            'isLockdown' => (bool) Cache::get('bot_lockdown_mode', false),
        ]);
    }

    public function toggleLockdown(Request $request): RedirectResponse
    {
        $current = (bool) Cache::get('bot_lockdown_mode', false);
        $new = ! $current;
        Cache::put('bot_lockdown_mode', $new, 86400);

        BotAuditLog::record(
            $new ? 'Enabled Emergency Lockdown' : 'Disabled Emergency Lockdown',
            'system',
            'lockdown_mode',
            ['active' => $current],
            ['active' => $new]
        );

        return back()->with('success', $new ? 'Emergency Panic Mode activated! High threat traffic will be challenged.' : 'Emergency Lockdown deactivated.');
    }

    public function flushThreatCache(): RedirectResponse
    {
        Cache::forget('bot_blocked_ips_list');
        Cache::forget('bot_allowed_ips_list');
        Cache::forget('bot_blocked_countries');
        Cache::forget('bot_active_security_rules');

        BotAuditLog::record('Flushed Security & Threat Cache', 'system', 'cache');

        return back()->with('success', 'Security threat cache successfully flushed.');
    }
}
