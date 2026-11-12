<?php

namespace App\Domains\Downloads\Listeners;

use App\Domains\Downloads\DownloadGrants;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderRefunded;
use App\Domains\Orders\Models\Order;

/**
 * Cancelled or fully refunded orders lose their download links.
 */
final class RevokeDownloads
{
    public function __construct(private readonly DownloadGrants $grants) {}

    public function handle(OrderCancelled|OrderRefunded $event): void
    {
        $order = Order::query()->find($event->orderId);

        if ($order !== null && ($order->status === OrderStatus::Cancelled || $order->payment_status === PaymentStatus::Refunded)) {
            $this->grants->revokeFor($order);
        }
    }
}
