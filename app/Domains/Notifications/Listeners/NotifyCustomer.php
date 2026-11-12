<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Downloads\DownloadGrants;
use App\Domains\Notifications\StoreNotifier;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderDelivered;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Events\OrderRefunded;
use App\Domains\Orders\Events\OrderShipped;
use App\Domains\Orders\Events\OrderStatusChanged;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Events\Dispatcher;
use Throwable;

/**
 * Order updates in a signed-in customer's bell. Guest orders have no account
 * and keep their emails only.
 */
final class NotifyCustomer
{
    public function __construct(
        private readonly StoreNotifier $notifier,
        private readonly DownloadGrants $grants,
    ) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderPlaced::class => 'placed',
            OrderStatusChanged::class => 'statusChanged',
            OrderShipped::class => 'shipped',
            OrderDelivered::class => 'delivered',
            OrderCancelled::class => 'cancelled',
            OrderRefunded::class => 'refunded',
            OrderPaid::class => 'paid',
        ];
    }

    public function placed(OrderPlaced $event): void
    {
        // Pay-on-delivery orders are confirmed straight away.
        $this->notify($event->orderId, fn (Order $order) => $order->status === OrderStatus::Confirmed ? ['customer.order_confirmed', 'confirmed'] : null);
    }

    public function statusChanged(OrderStatusChanged $event): void
    {
        $kind = match ($event->kind) {
            'confirmed' => 'customer.order_confirmed',
            'out_for_delivery' => 'customer.out_for_delivery',
            'payment_failed' => 'customer.payment_failed',
            default => null,
        };

        if ($kind !== null) {
            $key = $event->kind === 'confirmed' ? 'confirmed' : $event->key;
            $this->notify($event->orderId, fn () => [$kind, $key]);
        }
    }

    public function shipped(OrderShipped $event): void
    {
        $this->notify($event->orderId, function (Order $order) {
            $shipment = $order->shipments()->latest('id')->first();

            return ['customer.order_shipped', 'shipped:'.$order->shipments()->count(), [
                'carrier' => $shipment?->carrier,
                'tracking' => $shipment?->tracking_number,
            ]];
        });
    }

    public function delivered(OrderDelivered $event): void
    {
        $this->notify($event->orderId, fn () => ['customer.delivered', 'delivered']);
    }

    public function cancelled(OrderCancelled $event): void
    {
        $this->notify($event->orderId, fn () => ['customer.cancelled', 'cancelled']);
    }

    public function refunded(OrderRefunded $event): void
    {
        $this->notify($event->orderId, fn (Order $order) => ['customer.refunded', 'refunded:'.$order->refunds()->count()]);
    }

    public function paid(OrderPaid $event): void
    {
        $this->notify($event->orderId, function (Order $order) {
            // Links are issued here too, so this never depends on listener order.
            $this->grants->issueFor($order);

            return $this->grants->linksFor($order) === [] ? null : ['customer.downloads_ready', 'downloads'];
        });
    }

    /**
     * @param  callable(Order): (array{0: string, 1: string, 2?: array<string, scalar|null>}|null)  $decide  kind, dedupe key, extra params
     */
    private function notify(int $orderId, callable $decide): void
    {
        try {
            $order = Order::query()->find($orderId);
            $user = $order?->user_id === null ? null : User::query()->find($order->user_id);

            if ($order === null || $user === null) {
                return;
            }

            $decision = $decide($order);

            if ($decision === null) {
                return;
            }

            [$kind, $key] = $decision;
            $params = ['number' => $order->number, ...($decision[2] ?? [])];
            $this->notifier->toCustomer($user, $kind, $params, route('account.orders.show', $order, false), "{$kind}:{$order->id}:{$key}");
        } catch (Throwable $e) {
            report($e);
        }
    }
}
