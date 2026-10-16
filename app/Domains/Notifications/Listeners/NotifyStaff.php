<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Customers\Events\CustomerRegistered;
use App\Domains\Customers\Models\Customer;
use App\Domains\Inventory\Events\InventoryLow;
use App\Domains\Notifications\StoreNotifier;
use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Events\OrderRefunded;
use App\Domains\Orders\Events\OrderStatusChanged;
use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use Illuminate\Events\Dispatcher;
use Throwable;

/**
 * Staff notifications for store events (bell, and email for those who want
 * it). A failure is reported and never breaks the event's other listeners.
 */
final class NotifyStaff
{
    public function __construct(private readonly StoreNotifier $notifier) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderPlaced::class => 'placed',
            OrderPaid::class => 'paid',
            OrderCancelled::class => 'cancelled',
            OrderRefunded::class => 'refunded',
            OrderStatusChanged::class => 'statusChanged',
            InventoryLow::class => 'lowStock',
            CustomerRegistered::class => 'newCustomer',
        ];
    }

    public function placed(OrderPlaced $event): void
    {
        $this->order($event->orderId, 'order.placed', 'order.placed');
    }

    public function paid(OrderPaid $event): void
    {
        $this->order($event->orderId, 'order.paid', 'order.paid');
    }

    public function cancelled(OrderCancelled $event): void
    {
        $this->order($event->orderId, 'order.cancelled', 'order.cancelled');
    }

    public function refunded(OrderRefunded $event): void
    {
        // Several refunds on one order each notify; key by the refund count.
        $order = Order::query()->find($event->orderId);
        $this->order($event->orderId, 'order.refunded', 'order.refunded:'.($order?->refunds()->count() ?? 0));
    }

    public function statusChanged(OrderStatusChanged $event): void
    {
        if ($event->kind === 'payment_failed') {
            $this->order($event->orderId, 'payment.failed', "payment.failed:{$event->key}");
        }
    }

    public function lowStock(InventoryLow $event): void
    {
        $this->safely(function () use ($event) {
            $variant = ProductVariant::query()->with('product')->find($event->variantId);

            if ($variant === null) {
                return;
            }

            $this->notifier->toStaff('stock.low', [
                'product' => $variant->displayName(),
                'sku' => $event->sku,
                'on_hand' => $event->onHand,
            ], route('admin.products.edit', $variant->product_id, false), "stock.low:{$event->variantId}:".now()->toDateString());
        });
    }

    public function newCustomer(CustomerRegistered $event): void
    {
        $this->safely(function () use ($event) {
            $customer = Customer::query()->find($event->customerId);

            if ($customer === null) {
                return;
            }

            $this->notifier->toStaff('customer.new', [
                'name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: $customer->email,
                'email' => $customer->email,
            ], route('admin.customers.show', $customer->id, false), "customer.new:{$customer->id}");
        });
    }

    private function order(int $orderId, string $kind, string $key): void
    {
        $this->safely(function () use ($orderId, $kind, $key) {
            $order = Order::query()->with('shippingAddress')->find($orderId);

            if ($order === null) {
                return;
            }

            $this->notifier->toStaff($kind, self::orderParams($order), route('admin.orders.show', $order, false), "{$key}:{$order->id}");
        });
    }

    /**
     * @return array<string, string>
     */
    public static function orderParams(Order $order): array
    {
        $address = $order->shippingAddress;
        $name = $address === null ? '' : trim($address->first_name.' '.$address->last_name);

        return [
            'number' => $order->number,
            'total' => Money::of($order->grand_total, $order->currency)->format(),
            'customer' => $name !== '' ? $name : (string) $order->email,
        ];
    }

    private function safely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
