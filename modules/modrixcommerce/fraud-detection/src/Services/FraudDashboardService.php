<?php

namespace EcoModular\FraudDetection\Services;

use EcoModular\FraudDetection\Models\FraudScore;
use Illuminate\Support\Facades\DB;

class FraudDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $totalAnalyzed = FraudScore::query()->count();
        $highRisk = FraudScore::query()->where('risk_level', 'high')->count();
        $mediumRisk = FraudScore::query()->where('risk_level', 'medium')->count();
        $lowRisk = FraudScore::query()->where('risk_level', 'low')->count();
        $fraudDetected = FraudScore::query()->where('review_status', 'rejected')->count();
        $pendingReview = FraudScore::query()->where('review_status', 'pending')->count();
        $underReview = FraudScore::query()->where('review_status', 'under_review')->count();
        $approved = FraudScore::query()->where('review_status', 'approved')->count();

        $averageScore = FraudScore::query()->avg('total_score') ?? 0;
        $fraudPreventedAmount = (float) FraudScore::query()->where('review_status', 'rejected')->sum('order_total');

        return [
            'total_analyzed' => $totalAnalyzed,
            'high_risk' => $highRisk,
            'high_risk_count' => $highRisk,
            'medium_risk' => $mediumRisk,
            'medium_risk_count' => $mediumRisk,
            'low_risk' => $lowRisk,
            'low_risk_count' => $lowRisk,
            'fraud_detected' => $fraudDetected,
            'pending_review' => $pendingReview,
            'under_review' => $underReview,
            'approved' => $approved,
            'approved_count' => $approved,
            'rejected_count' => $fraudDetected,
            'average_score' => round($averageScore, 1),
            'avg_score' => round($averageScore, 1),
            'detection_rate' => $totalAnalyzed > 0 ? round(($highRisk / $totalAnalyzed) * 100, 1) : 0,
            'high_risk_pct' => $totalAnalyzed > 0 ? round(($highRisk / $totalAnalyzed) * 100, 1) : 0,
            'fraud_prevented_amount' => round($fraudPreventedAmount, 2),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRiskTrends(int $days = 30): array
    {
        return FraudScore::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as high_risk"),
                DB::raw("SUM(CASE WHEN risk_level = 'medium' THEN 1 ELSE 0 END) as medium_risk"),
                DB::raw("SUM(CASE WHEN risk_level = 'low' THEN 1 ELSE 0 END) as low_risk"),
                DB::raw('ROUND(AVG(total_score), 1) as avg_score'),
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total' => (int) $row->total,
                'high_risk' => (int) $row->high_risk,
                'medium_risk' => (int) $row->medium_risk,
                'low_risk' => (int) $row->low_risk,
                'avg_score' => (float) $row->avg_score,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTopIndicators(int $limit = 10): array
    {
        $scores = FraudScore::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->pluck('indicators');

        $indicatorCounts = [];
        foreach ($scores as $indicators) {
            if (! is_array($indicators)) {
                continue;
            }
            foreach ($indicators as $indicator) {
                $key = $indicator['key'] ?? 'unknown';
                $label = $indicator['label'] ?? $key;
                if (! isset($indicatorCounts[$key])) {
                    $indicatorCounts[$key] = ['key' => $key, 'label' => $label, 'count' => 0, 'avg_score' => 0, 'total_score' => 0];
                }
                $indicatorCounts[$key]['count']++;
                $indicatorCounts[$key]['total_score'] += ($indicator['score'] ?? 0);
            }
        }

        foreach ($indicatorCounts as &$item) {
            $item['avg_score'] = $item['count'] > 0 ? round($item['total_score'] / $item['count'], 1) : 0;
            unset($item['total_score']);
        }
        unset($item);

        usort($indicatorCounts, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($indicatorCounts, 0, $limit);
    }

    /**
     * @return array<string, int>
     */
    public function getRiskDistribution(): array
    {
        $results = FraudScore::query()
            ->select('risk_level', DB::raw('COUNT(*) as count'))
            ->groupBy('risk_level')
            ->pluck('count', 'risk_level')
            ->all();

        return [
            'low' => (int) ($results['low'] ?? 0),
            'medium' => (int) ($results['medium'] ?? 0),
            'high' => (int) ($results['high'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentHighRisk(int $limit = 10): array
    {
        return FraudScore::query()
            ->with(['order', 'customer'])
            ->where('risk_level', 'high')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (FraudScore $s) => [
                'id' => $s->id,
                'order_id' => $s->order_id,
                'order_number' => $s->order?->number ?? '#'.$s->order_id,
                'customer_email' => $s->customer_email,
                'total_score' => $s->total_score,
                'risk_level' => $s->risk_level,
                'review_status' => $s->review_status,
                'indicators_count' => is_array($s->indicators) ? count($s->indicators) : 0,
                'created_at' => $s->created_at?->diffForHumans(),
                'created_date' => $s->created_at?->toFormattedDateString(),
            ])
            ->all();
    }
}
