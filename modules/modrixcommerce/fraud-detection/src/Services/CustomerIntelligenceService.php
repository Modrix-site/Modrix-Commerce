<?php

namespace EcoModular\FraudDetection\Services;

use EcoModular\FraudDetection\Models\FraudCustomerProfile;
use EcoModular\FraudDetection\Models\FraudScore;

class CustomerIntelligenceService
{
    /**
     * Update or create a customer fraud profile after an order is scored.
     */
    public function updateProfile(FraudScore $fraudScore): void
    {
        if (! $fraudScore->customer_id) {
            return;
        }

        $profile = FraudCustomerProfile::firstOrCreate(
            ['customer_id' => $fraudScore->customer_id],
            ['trust_score' => 50, 'total_orders' => 0, 'risk_history' => []]
        );

        $profile->total_orders++;
        $profile->last_order_at = now();

        if ($fraudScore->risk_level === FraudScore::RISK_HIGH) {
            $profile->flagged_orders++;
        }

        if ($fraudScore->review_status === FraudScore::STATUS_REJECTED) {
            $profile->rejected_orders++;
        }

        if ($fraudScore->review_status === FraudScore::STATUS_APPROVED) {
            $profile->approved_orders++;
        }

        $profile->total_revenue = bcadd((string) $profile->total_revenue, (string) $fraudScore->order_total, 4);

        // Append to risk history (keep last 50 entries)
        $history = $profile->risk_history ?? [];
        $history[] = [
            'date' => now()->toIso8601String(),
            'score' => $fraudScore->total_score,
            'order_id' => $fraudScore->order_id,
            'risk_level' => $fraudScore->risk_level,
        ];
        $profile->risk_history = array_slice($history, -50);

        $profile->recalculateTrustScore();
        $profile->save();
    }

    /**
     * Re-sync a customer profile after a review action (approve/reject).
     */
    public function syncAfterReview(FraudScore $fraudScore, string $previousStatus, string $newStatus): void
    {
        if (! $fraudScore->customer_id) {
            return;
        }

        $profile = FraudCustomerProfile::query()
            ->where('customer_id', $fraudScore->customer_id)
            ->first();

        if (! $profile) {
            return;
        }

        // Undo previous status count
        if ($previousStatus === FraudScore::STATUS_APPROVED) {
            $profile->approved_orders = max(0, $profile->approved_orders - 1);
        }
        if ($previousStatus === FraudScore::STATUS_REJECTED) {
            $profile->rejected_orders = max(0, $profile->rejected_orders - 1);
        }

        // Apply new status count
        if ($newStatus === FraudScore::STATUS_APPROVED) {
            $profile->approved_orders++;
        }
        if ($newStatus === FraudScore::STATUS_REJECTED) {
            $profile->rejected_orders++;
        }

        $profile->recalculateTrustScore();
        $profile->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomerProfile(int $customerId): ?array
    {
        $profile = FraudCustomerProfile::query()
            ->with('customer')
            ->where('customer_id', $customerId)
            ->first();

        if (! $profile) {
            return null;
        }

        return [
            'id' => $profile->id,
            'customer_id' => $profile->customer_id,
            'customer_name' => $profile->customer?->name ?? 'Unknown',
            'customer_email' => $profile->customer?->email ?? '',
            'trust_score' => $profile->trust_score,
            'trust_level' => $profile->trustLevel(),
            'total_orders' => $profile->total_orders,
            'flagged_orders' => $profile->flagged_orders,
            'rejected_orders' => $profile->rejected_orders,
            'approved_orders' => $profile->approved_orders,
            'total_revenue' => number_format((float) $profile->total_revenue, 2),
            'is_repeat_offender' => $profile->is_repeat_offender,
            'last_order_at' => $profile->last_order_at?->diffForHumans(),
            'risk_history' => $profile->risk_history ?? [],
        ];
    }
}
