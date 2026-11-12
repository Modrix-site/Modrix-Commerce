<?php

namespace App\Domains\Orders\Listeners;

use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Models\Order;
use Throwable;

/**
 * Every paid order gets its invoice. Idempotent: a repeated event returns
 * the invoice already issued. A failure is reported but never stops the
 * other OrderPaid listeners (the payment email issues the invoice again).
 */
final class IssueInvoiceOnPayment
{
    public function __construct(private readonly OrderDocuments $documents) {}

    public function handle(OrderPaid $event): void
    {
        $order = Order::query()->find($event->orderId);

        if ($order === null) {
            return;
        }

        try {
            $this->documents->issueInvoice($order);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
