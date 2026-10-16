<?php

namespace App\Domains\Downloads\Listeners;

use App\Domains\Downloads\DownloadGrants;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;

/**
 * A paid order gets its download links; an order of digital products only is
 * then delivered (nothing to ship). Idempotent.
 */
final class GrantDownloads
{
    public function __construct(private readonly DownloadGrants $grants, private readonly OrderWorkflow $workflow) {}

    public function handle(OrderPaid $event): void
    {
        $order = Order::query()->find($event->orderId);

        if ($order === null) {
            return;
        }

        $this->grants->issueFor($order);

        if ($order->fulfillment_status === FulfillmentStatus::Unfulfilled && $this->grants->isDigitalOnly($order)) {
            $this->workflow->deliverDigital($order);
        }
    }
}
