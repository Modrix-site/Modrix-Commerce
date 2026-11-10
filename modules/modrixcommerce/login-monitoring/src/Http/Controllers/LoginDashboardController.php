<?php

namespace EcoModular\LoginMonitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\LoginMonitoring\Models\LoginEvent;
use EcoModular\LoginMonitoring\Models\LoginFailedAttempt;
use EcoModular\LoginMonitoring\Models\LoginSecurityIncident;
use EcoModular\LoginMonitoring\Models\LoginUserSession;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LoginDashboardController extends Controller
{
    public function index(): Response
    {
        $now = now();
        $past24h = $now->copy()->subHours(24);

        // Core KPIs
        $totalAttempts24h = LoginEvent::query()->where('created_at', '>=', $past24h)->count();
        $successfulLogins24h = LoginEvent::query()->where('created_at', '>=', $past24h)->where('status', 'success')->count();
        $failedLogins24h = LoginEvent::query()->where('created_at', '>=', $past24h)->where('status', 'failed')->count();
        $suspiciousLogins24h = LoginEvent::query()->where('created_at', '>=', $past24h)->where('is_suspicious', true)->count();

        $activeSessions = LoginUserSession::query()
            ->where('is_revoked', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        $openIncidents = LoginSecurityIncident::query()
            ->whereIn('status', ['open', 'investigating'])
            ->count();

        $currentlyLockedAccounts = LoginFailedAttempt::query()
            ->where('is_locked_out', true)
            ->where('locked_until', '>', now())
            ->count();

        // Daily trend (last 7 days)
        $dailyTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $start = $day->copy()->startOfDay();
            $end = $day->copy()->endOfDay();

            $dailyTrends[] = [
                'date' => $day->format('M d'),
                'success' => LoginEvent::query()->whereBetween('created_at', [$start, $end])->where('status', 'success')->count(),
                'failed' => LoginEvent::query()->whereBetween('created_at', [$start, $end])->where('status', 'failed')->count(),
                'suspicious' => LoginEvent::query()->whereBetween('created_at', [$start, $end])->where('is_suspicious', true)->count(),
            ];
        }

        // Top geographic origins
        $geographicLogins = LoginEvent::query()
            ->selectRaw("COALESCE(country_code, 'Unknown') as country, COUNT(*) as count")
            ->where('created_at', '>=', $past24h)
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(7)
            ->get()
            ->map(fn ($r) => [
                'country' => $r->country,
                'count' => (int) $r->count,
            ])
            ->values();

        // Threat level distribution
        $threatLevels = [
            'low' => LoginEvent::query()->where('created_at', '>=', $past24h)->where('threat_level', 'low')->count(),
            'medium' => LoginEvent::query()->where('created_at', '>=', $past24h)->where('threat_level', 'medium')->count(),
            'high' => LoginEvent::query()->where('created_at', '>=', $past24h)->where('threat_level', 'high')->count(),
            'critical' => LoginEvent::query()->where('created_at', '>=', $past24h)->where('threat_level', 'critical')->count(),
        ];

        // Recent login events (10)
        $recentEvents = LoginEvent::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'uuid' => $e->event_uuid,
                'email' => $e->email,
                'user_name' => $e->user?->name,
                'ip_address' => $e->ip_address,
                'status' => $e->status,
                'failure_reason' => $e->failure_reason,
                'risk_score' => $e->risk_score,
                'threat_level' => $e->threat_level,
                'browser' => $e->browser,
                'operating_system' => $e->operating_system,
                'device_type' => $e->device_type,
                'country_code' => $e->country_code,
                'city' => $e->city,
                'is_suspicious' => (bool) $e->is_suspicious,
                'created_at' => $e->created_at?->diffForHumans() ?? 'Just now',
                'timestamp' => $e->created_at?->toIso8601String(),
            ]);

        return Inertia::render('module/modrixcommerce/login-monitoring/admin/dashboard', [
            'stats' => [
                'total_attempts_24h' => $totalAttempts24h,
                'successful_logins_24h' => $successfulLogins24h,
                'failed_logins_24h' => $failedLogins24h,
                'suspicious_logins_24h' => $suspiciousLogins24h,
                'active_sessions' => $activeSessions,
                'open_incidents' => $openIncidents,
                'currently_locked_accounts' => $currentlyLockedAccounts,
                'success_rate' => $totalAttempts24h > 0 ? round(($successfulLogins24h / $totalAttempts24h) * 100, 1) : 100,
            ],
            'dailyTrends' => $dailyTrends,
            'geographicLogins' => $geographicLogins,
            'threatLevels' => $threatLevels,
            'recentEvents' => $recentEvents,
        ]);
    }
}
