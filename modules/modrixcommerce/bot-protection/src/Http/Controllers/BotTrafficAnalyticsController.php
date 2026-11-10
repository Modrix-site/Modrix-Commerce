<?php

namespace EcoModular\BotProtection\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\BotProtection\Models\BotTrafficLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BotTrafficAnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $timeframe = $request->query('timeframe', '7d');
        $days = match ($timeframe) {
            '24h' => 1,
            '30d' => 30,
            default => 7,
        };

        $startDate = Carbon::now()->subDays($days);

        $totalCount = BotTrafficLog::query()->where('created_at', '>=', $startDate)->count();
        $botCount = BotTrafficLog::query()->where('created_at', '>=', $startDate)->where('is_bot', true)->count();
        $humanCount = max(0, $totalCount - $botCount);

        // Status codes breakdown
        $statusCodes = BotTrafficLog::query()
            ->select('status_code', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('status_code')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'status_code')
            ->toArray();

        // Client Classifications
        $classifications = BotTrafficLog::query()
            ->select('classification', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('classification')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'classification')
            ->toArray();

        // Geographic distribution
        $geoBreakdown = BotTrafficLog::query()
            ->select('country_code', DB::raw('count(*) as count'), DB::raw('sum(case when is_bot = 1 then 1 else 0 end) as bot_count'))
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('country_code')
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        // Daily traffic timeline
        $timeline = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            $dayTotal = BotTrafficLog::query()->whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $dayBots = BotTrafficLog::query()->whereBetween('created_at', [$dayStart, $dayEnd])->where('is_bot', true)->count();

            $timeline[] = [
                'date' => $day->format('M d'),
                'total' => $dayTotal,
                'bots' => $dayBots,
                'human' => max(0, $dayTotal - $dayBots),
            ];
        }

        return Inertia::render('module/modrixcommerce/bot-protection/admin/traffic-analytics', [
            'timeframe' => $timeframe,
            'summary' => [
                'total' => $totalCount,
                'human' => $humanCount,
                'bots' => $botCount,
                'bot_percentage' => $totalCount > 0 ? round(($botCount / $totalCount) * 100, 1) : 0,
            ],
            'statusCodes' => $statusCodes,
            'classifications' => $classifications,
            'geoBreakdown' => $geoBreakdown,
            'timeline' => $timeline,
        ]);
    }
}
