<?php

namespace EcoModular\BotProtection\Services;

use EcoModular\BotProtection\Models\BotScore;

class AnomalyLearningService
{
    public function getHistoricalProfile(string $ip): ?BotScore
    {
        return BotScore::query()->where('ip_address', $ip)->first();
    }

    public function recordRequestAndViolation(string $ip, int $newRiskPoints, ?string $countryCode = null, ?array $heuristicFactor = null): BotScore
    {
        $profile = BotScore::query()->firstOrNew(['ip_address' => $ip]);

        $profile->total_requests = ($profile->total_requests ?? 0) + 1;
        if ($newRiskPoints > 20) {
            $profile->violation_count = ($profile->violation_count ?? 0) + 1;
        }

        // Calculate moving average adaptive score
        $currentScore = $profile->overall_score ?? 0;
        $profile->overall_score = min(100, (int) round(($currentScore * 0.7) + ($newRiskPoints * 0.3)));

        // Threat level
        $profile->threat_level = match (true) {
            $profile->overall_score >= 80 => 'critical',
            $profile->overall_score >= 60 => 'high',
            $profile->overall_score >= 30 => 'medium',
            default => 'low',
        };

        if ($countryCode) {
            $profile->country_code = $countryCode;
        }

        if ($heuristicFactor) {
            $factors = $profile->heuristic_factors ?? [];
            $factors[] = [
                'factor' => $heuristicFactor,
                'at' => now()->toIso8601String(),
            ];
            $profile->heuristic_factors = array_slice($factors, -10); // keep last 10 factors
        }

        $profile->last_seen_at = now();
        $profile->save();

        return $profile;
    }

    public function getAdaptiveRiskModifier(string $ip): int
    {
        $profile = BotScore::query()->where('ip_address', $ip)->first();
        if (! $profile) {
            return 0;
        }

        // Adaptive risk penalty based on historical violations
        if ($profile->violation_count > 10) {
            return 35;
        }
        if ($profile->violation_count > 5) {
            return 20;
        }
        if ($profile->violation_count > 2) {
            return 10;
        }

        return 0;
    }
}
