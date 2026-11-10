<?php

namespace EcoModular\RefundManagement\Listeners;

use App\Domains\Orders\Events\OrderRefunded;
use App\Domains\Orders\Models\Refund;
use EcoModular\RefundManagement\RefundManagementService;
use Throwable;

/**
 * Ensures every refund processed anywhere in Modrix Commerce (including the
 * core order view or gateway webhooks) receives a payment tracking entry and,
 * when enabled, an immutable credit memo.
 */
final class HandleOrderRefunded
{
    public function __construct(private readonly RefundManagementService $service) {}

    public function handle(OrderRefunded $event): void
    {
        $refunds = Refund::query()
            ->where('order_id', $event->orderId)
            ->orderBy('id')
            ->get();

        foreach ($refunds as $refund) {
            try {
                $this->service->recordPaymentTrack($refund);

                if ($this->service->autoGenerateCreditMemo() && $refund->status !== Refund::STATUS_FAILED) {
                    $this->service->issueCreditMemo($refund);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
