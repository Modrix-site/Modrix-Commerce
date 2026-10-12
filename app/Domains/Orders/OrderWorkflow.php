<?php

namespace App\Domains\Orders;

use App\Core\Audit\AuditLogger;
use App\Domains\Discounts\Models\Coupon;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\MovementReason;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderDelivered;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Events\OrderRefunded;
use App\Domains\Orders\Events\OrderShipped;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\Refund;
use App\Domains\Orders\Models\RefundItem;
use App\Domains\Orders\Models\Shipment;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only code that changes an order's state after placement.
 *
 * Each transition locks the order row, checks that the move is allowed from
 * the current state, applies it in one transaction, appends order history and
 * an audit entry, and dispatches a domain event after commit. There is no
 * generic "set status" operation.
 */
final class OrderWorkflow
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PaymentMethods $paymentMethods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<string> actions currently allowed (for the admin UI)
     */
    public function allowedActions(Order $order): array
    {
        $actions = [];

        if ($this->canRecordPayment($order)) {
            $actions[] = 'record_payment';
        }

        if ($this->canShip($order)) {
            $actions[] = 'ship';
        }

        if ($order->fulfillment_status === FulfillmentStatus::Shipped && $order->status !== OrderStatus::Cancelled) {
            $actions[] = 'mark_delivered';
        }

        if ($this->canCancel($order)) {
            $actions[] = 'cancel';
        }

        if ($order->refundable()->isPositive()) {
            $actions[] = 'refund';
        }

        return $actions;
    }

    /**
     * Record a payment received outside the platform (manual methods).
     */
    public function recordManualPayment(Order $order, User $actor, ?string $reference = null, ?string $note = null): Order
    {
        return $this->transition($order, function (Order $order) use ($actor, $reference, $note): Order {
            if (! $this->canRecordPayment($order)) {
                throw ValidationException::withMessages(['order' => 'A payment cannot be recorded for this order in its current state.']);
            }

            if ($this->paymentMethods->definition($order->payment_method_code) === null || ! isset(PaymentMethods::MANUAL[$order->payment_method_code])) {
                throw ValidationException::withMessages(['order' => 'Payments for this method are confirmed by the payment provider, not recorded manually.']);
            }

            $amount = $order->money('grand_total');

            $payment = new Payment;
            $payment->forceFill([
                'order_id' => $order->id,
                'method_code' => $order->payment_method_code,
                'provider' => 'manual',
                'provider_reference' => $reference,
                'amount' => $amount->toDecimal(),
                'currency' => $order->currency,
                'status' => Payment::STATUS_PAID,
                'recorded_by' => $actor->id,
                'note' => $note,
                'paid_at' => now(),
            ])->save();

            $order->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'paid_total' => $amount->toDecimal(),
                'paid_at' => now(),
                'status' => $order->fulfillment_status === FulfillmentStatus::Delivered ? OrderStatus::Completed : OrderStatus::Processing,
                'completed_at' => $order->fulfillment_status === FulfillmentStatus::Delivered ? now() : null,
            ])->save();

            $this->log($order, 'payment', "Payment of {$amount->format()} recorded ({$order->payment_method_name}).", true, $actor, ['reference' => $reference]);
            $this->audit->record('order.payment_recorded', $order, ['number' => $order->number, 'amount' => $amount->toDecimal()], $actor);

            DB::afterCommit(fn () => OrderPaid::dispatch($order->id));

            return $order;
        });
    }

    public function ship(Order $order, User $actor, ?string $carrier, ?string $trackingNumber, ?string $trackingUrl): Order
    {
        return $this->transition($order, function (Order $order) use ($actor, $carrier, $trackingNumber, $trackingUrl): Order {
            if (! $this->canShip($order)) {
                throw ValidationException::withMessages(['order' => 'This order cannot be shipped in its current state (it may need to be paid first).']);
            }

            $shipment = new Shipment;
            $shipment->forceFill([
                'order_id' => $order->id,
                'carrier' => $carrier,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'actor_id' => $actor->id,
                'shipped_at' => now(),
            ])->save();

            $order->forceFill([
                'fulfillment_status' => FulfillmentStatus::Shipped,
                'shipped_at' => now(),
                'status' => OrderStatus::Processing,
            ])->save();

            $tracking = $trackingNumber ? " Tracking: {$trackingNumber}".($carrier ? " ({$carrier})" : '').'.' : '';
            $this->log($order, 'fulfillment', "Order shipped.{$tracking}", true, $actor);
            $this->audit->record('order.shipped', $order, ['number' => $order->number, 'carrier' => $carrier, 'tracking' => $trackingNumber], $actor);

            DB::afterCommit(fn () => OrderShipped::dispatch($order->id));

            return $order;
        });
    }

    public function markDelivered(Order $order, User $actor): Order
    {
        return $this->transition($order, function (Order $order) use ($actor): Order {
            if ($order->fulfillment_status !== FulfillmentStatus::Shipped || $order->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages(['order' => 'Only shipped orders can be marked as delivered.']);
            }

            $paid = $order->payment_status !== PaymentStatus::Unpaid;

            $order->forceFill([
                'fulfillment_status' => FulfillmentStatus::Delivered,
                'delivered_at' => now(),
                'status' => $paid ? OrderStatus::Completed : OrderStatus::Processing,
                'completed_at' => $paid ? now() : null,
            ])->save();
            Shipment::query()->where('order_id', $order->id)->whereNull('delivered_at')->update(['delivered_at' => now()]);

            $this->log($order, 'fulfillment', 'Order delivered.', true, $actor);
            $this->audit->record('order.delivered', $order, ['number' => $order->number], $actor);

            DB::afterCommit(fn () => OrderDelivered::dispatch($order->id));

            return $order;
        });
    }

    /**
     * Cancel an unfulfilled order that has no outstanding payment. Stock is
     * returned and the coupon use is released.
     */
    public function cancel(Order $order, ?User $actor, string $reason): Order
    {
        return $this->transition($order, function (Order $order) use ($actor, $reason): Order {
            if (! $this->canCancel($order)) {
                throw ValidationException::withMessages(['order' => 'Only unshipped orders without an unrefunded payment can be cancelled. Refund the payment first.']);
            }

            $this->restockRemaining($order, MovementReason::Cancellation, $actor);

            if ($order->coupon_code !== null) {
                Coupon::withTrashed()->where('code', $order->coupon_code)->where('times_used', '>', 0)->decrement('times_used');
            }

            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            $this->log($order, 'status', "Order cancelled: {$reason}", true, $actor);
            $this->audit->record('order.cancelled', $order, ['number' => $order->number, 'reason' => $reason], $actor);

            DB::afterCommit(fn () => OrderCancelled::dispatch($order->id));

            return $order;
        });
    }

    /**
     * Record a refund. For manual methods the money is returned outside the
     * platform; this records it for reconciliation and optionally restocks items.
     *
     * @param  array<int, int>  $restock  order item id => quantity to put back into stock
     */
    public function refund(Order $order, User $actor, Money $amount, string $reason, array $restock = []): Refund
    {
        return $this->transition($order, function (Order $order) use ($actor, $amount, $reason, $restock): Refund {
            if (! $amount->isPositive() || $amount->currency() !== $order->currency) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount in the order currency.']);
            }

            if ($amount->greaterThan($order->refundable())) {
                throw ValidationException::withMessages(['amount' => "At most {$order->refundable()->format()} can be refunded."]);
            }

            $refund = new Refund;
            $refund->forceFill([
                'order_id' => $order->id,
                'payment_id' => $order->payments()->latest('id')->value('id'),
                'amount' => $amount->toDecimal(),
                'currency' => $order->currency,
                'reason' => $reason,
                'status' => Refund::STATUS_COMPLETED,
                'actor_id' => $actor->id,
            ])->save();

            $items = $order->items()->get()->keyBy('id');
            $toRelease = [];

            foreach ($restock as $itemId => $quantity) {
                /** @var OrderItem|null $item */
                $item = $items->get($itemId);
                $quantity = (int) $quantity;

                if ($item === null || $quantity <= 0) {
                    continue;
                }

                if ($quantity > $item->quantity - $item->restocked_quantity) {
                    throw ValidationException::withMessages(['restock' => "Cannot restock more than was sold for {$item->sku}."]);
                }

                RefundItem::query()->forceCreate(['refund_id' => $refund->id, 'order_item_id' => $item->id, 'quantity' => $quantity, 'restocked' => true]);
                $item->forceFill(['restocked_quantity' => $item->restocked_quantity + $quantity])->save();

                if ($item->variant_id !== null) {
                    $toRelease[$item->variant_id] = ($toRelease[$item->variant_id] ?? 0) + $quantity;
                }
            }

            if ($toRelease !== []) {
                $this->inventory->release($toRelease, MovementReason::Restock, $order, $actor);
            }

            $refunded = $order->money('refunded_total')->plus($amount);
            $fullyRefunded = ! $refunded->lessThan($order->money('paid_total'));

            $order->forceFill([
                'refunded_total' => $refunded->toDecimal(),
                'payment_status' => $fullyRefunded ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ])->save();

            $this->log($order, 'refund', "Refund of {$amount->format()} recorded: {$reason}", true, $actor);
            $this->audit->record('order.refunded', $order, ['number' => $order->number, 'amount' => $amount->toDecimal(), 'restocked' => $restock], $actor);

            DB::afterCommit(fn () => OrderRefunded::dispatch($order->id));

            return $refund;
        });
    }

    public function addNote(Order $order, User $actor, string $message, bool $customerVisible): void
    {
        $this->log($order, 'note', $message, $customerVisible, $actor);
    }

    private function canRecordPayment(Order $order): bool
    {
        return $order->payment_status === PaymentStatus::Unpaid && $order->status !== OrderStatus::Cancelled;
    }

    private function canShip(Order $order): bool
    {
        if ($order->fulfillment_status !== FulfillmentStatus::Unfulfilled || $order->status === OrderStatus::Cancelled) {
            return false;
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return true;
        }

        return $order->payment_status === PaymentStatus::Unpaid
            && ($this->paymentMethods->definition($order->payment_method_code)?->allowsFulfillmentBeforePayment() ?? false);
    }

    private function canCancel(Order $order): bool
    {
        return $order->status !== OrderStatus::Cancelled
            && $order->status !== OrderStatus::Completed
            && $order->fulfillment_status === FulfillmentStatus::Unfulfilled
            && in_array($order->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Refunded], true);
    }

    private function restockRemaining(Order $order, MovementReason $reason, ?User $actor): void
    {
        $toRelease = [];

        foreach ($order->items()->get() as $item) {
            $remaining = $item->quantity - $item->restocked_quantity;

            if ($remaining > 0 && $item->variant_id !== null) {
                $toRelease[$item->variant_id] = ($toRelease[$item->variant_id] ?? 0) + $remaining;
                $item->forceFill(['restocked_quantity' => $item->quantity])->save();
            }
        }

        if ($toRelease !== []) {
            $this->inventory->release($toRelease, $reason, $order, $actor);
        }
    }

    /**
     * Runs a change against the freshly locked order row.
     *
     * @template T
     *
     * @param  callable(Order): T  $change
     * @return T
     */
    private function transition(Order $order, callable $change): mixed
    {
        return DB::transaction(function () use ($order, $change) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            return $change($locked);
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function log(Order $order, string $type, string $message, bool $customerVisible, ?User $actor, array $metadata = []): void
    {
        OrderEvent::query()->forceCreate([
            'order_id' => $order->id,
            'type' => $type,
            'message' => $message,
            'is_customer_visible' => $customerVisible,
            'actor_id' => $actor?->id,
            'metadata' => $metadata === [] ? null : AuditLogger::redact($metadata),
        ]);
    }
}
