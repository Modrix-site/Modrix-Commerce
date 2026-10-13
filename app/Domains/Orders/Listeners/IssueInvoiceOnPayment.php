<?php

namespace App\Domains\Orders\Listeners;

use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Models\Order;

/**
 * Every paid order gets its invoice. Idempotent: a repeated event returns
 * the invoice already issued.
 */
final class IssueInvoiceOnPayment
{
    public function __construct(private readonly OrderDocuments $documents) {}

    public function handle(OrderPaid $event): void
    {
        $order = Order::query()->find($event->orderId);

        if ($order !== null) {
            $this->documents->issueInvoice($order);
        }
    }
}
