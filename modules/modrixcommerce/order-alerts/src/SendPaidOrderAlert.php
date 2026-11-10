<?php

namespace EcoModular\OrderAlerts;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Queued and idempotent: OrderPaid may be delivered more than once, so each
 * order is alerted at most once (a lock prevents concurrent duplicates, the
 * delivery row prevents repeats after retries).
 */
final class SendPaidOrderAlert implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly ModuleSettingsSchema $settings) {}

    public function handle(OrderPaid $event): void
    {
        $recipient = $this->settings->of(OrderAlertsServiceProvider::MODULE, 'recipient');
        $order = Order::query()->find($event->orderId);

        if (! is_string($recipient) || $recipient === '' || $order === null) {
            return;
        }

        $minimum = Money::of((int) $this->settings->of(OrderAlertsServiceProvider::MODULE, 'minimum_total', 0), $order->currency);

        if ($order->money('grand_total')->lessThan($minimum)) {
            return;
        }

        $lock = Cache::lock("order-alerts:{$order->id}", 30);

        if (! $lock->get()) {
            return;
        }

        try {
            if (DB::table('order_alert_deliveries')->where('order_id', $order->id)->exists()) {
                return;
            }

            $total = $order->money('grand_total')->format();
            Mail::raw(
                "Order {$order->number} was paid: {$total}.\n\nCustomer: {$order->email}\nItems: {$order->items()->sum('quantity')}",
                fn (Message $message) => $message->to($recipient)->subject("Paid order {$order->number} ({$total})"),
            );

            DB::table('order_alert_deliveries')->insertOrIgnore([
                'order_id' => $order->id,
                'recipient' => $recipient,
                'sent_at' => now(),
            ]);
        } finally {
            $lock->release();
        }
    }
}
