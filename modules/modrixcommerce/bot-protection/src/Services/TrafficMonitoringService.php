<?php

namespace EcoModular\BotProtection\Services;

use EcoModular\BotProtection\Models\BotBlockedIp;
use EcoModular\BotProtection\Models\BotEvent;
use EcoModular\BotProtection\Models\BotTrafficLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TrafficMonitoringService
{
    public function logRequest(Request $request, array $evaluation, int $statusCode, int $responseTimeMs): void
    {
        try {
            BotTrafficLog::create([
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'request_uri' => substr($request->path(), 0, 500),
                'method' => $request->method(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'status_code' => $statusCode,
                'response_time_ms' => $responseTimeMs,
                'bot_score' => $evaluation['score'] ?? 0,
                'is_bot' => $evaluation['is_bot'] ?? false,
                'classification' => $evaluation['bot_type'] ?? 'human',
                'action' => $evaluation['action'] ?? 'allow',
                'country_code' => $request->header('cf-ipcountry') ?: null,
                'referer' => substr((string) $request->header('referer'), 0, 500),
            ]);
        } catch (\Throwable) {
            // Silently continue if log fails
        }
    }

    public function getDashboardStats(): array
    {
        $today = Carbon::today();

        $totalRequests = BotTrafficLog::query()->where('created_at', '>=', $today)->count();
        $botRequests = BotTrafficLog::query()->where('created_at', '>=', $today)->where('is_bot', true)->count();
        $blockedRequests = BotTrafficLog::query()->where('created_at', '>=', $today)->where('action', 'block')->count();
        $challengedRequests = BotTrafficLog::query()->where('created_at', '>=', $today)->where('action', 'challenge')->count();
        $humanRequests = max(0, $totalRequests - $botRequests);

        $threatRate = $totalRequests > 0 ? round(($botRequests / $totalRequests) * 100, 1) : 0;
        $activeBlockedIps = BotBlockedIp::query()->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->count();

        $unresolvedIncidents = BotEvent::query()->where('is_resolved', false)->count();

        return [
            'total_requests' => $totalRequests,
            'human_requests' => $humanRequests,
            'suspected_bots' => $botRequests,
            'blocked_requests' => $blockedRequests,
            'challenged_requests' => $challengedRequests,
            'threat_rate' => $threatRate,
            'active_blocked_ips' => $activeBlockedIps,
            'unresolved_incidents' => $unresolvedIncidents,
            'is_lockdown_active' => (bool) Cache::get('bot_lockdown_mode', false),
        ];
    }

    public function getHourlyThreatTrends(): array
    {
        $hours = [];
        $now = Carbon::now();

        for ($i = 23; $i >= 0; $i--) {
            $hourStart = $now->copy()->subHours($i)->startOfHour();
            $hourEnd = $hourStart->copy()->endOfHour();

            $total = BotTrafficLog::query()
                ->whereBetween('created_at', [$hourStart, $hourEnd])
                ->count();

            $blocked = BotTrafficLog::query()
                ->whereBetween('created_at', [$hourStart, $hourEnd])
                ->where('action', 'block')
                ->count();

            $challenged = BotTrafficLog::query()
                ->whereBetween('created_at', [$hourStart, $hourEnd])
                ->where('action', 'challenge')
                ->count();

            $hours[] = [
                'hour' => $hourStart->format('H:00'),
                'total' => $total,
                'blocked' => $blocked,
                'challenged' => $challenged,
                'human' => max(0, $total - ($blocked + $challenged)),
            ];
        }

        return $hours;
    }

    public function getTopAttackingIps(int $limit = 8): array
    {
        return BotEvent::query()
            ->select('ip_address', 'country_code', 'bot_type', DB::raw('count(*) as count'), DB::raw('max(risk_score) as max_score'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('ip_address', 'country_code', 'bot_type')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getTopTargetedPages(int $limit = 8): array
    {
        return BotTrafficLog::query()
            ->select('request_uri', DB::raw('count(*) as total_hits'), DB::raw('sum(case when is_bot = 1 then 1 else 0 end) as bot_hits'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('request_uri')
            ->orderByDesc('bot_hits')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getThreatDistribution(): array
    {
        $types = BotEvent::query()
            ->select('bot_type', DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('bot_type')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'bot_type')
            ->toArray();

        return $types ?: [
            'scraper' => 0,
            'credential_stuffing' => 0,
            'checkout_bot' => 0,
            'inventory_hoarder' => 0,
            'bad_user_agent' => 0,
            'headless_or_cli' => 0,
        ];
    }

    public function getRecentIncidents(int $limit = 10): array
    {
        return BotEvent::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
