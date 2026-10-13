<?php

namespace App\Domains\Orders;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\SettingsRepository;
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
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\Refund;
use App\Domains\Orders\Models\RefundItem;
use App\Domains\Orders\Models\Shipment;
use App\Domains\Payments\Gateway\PaymentUpdate;
use App\Domains\Payments\Gateway\RefundUpdate;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @return list<string> actions currently allowed (for the admin UI)
     */
    public function allowedActions(Order $order): array
    {
        $actions = [];

        if ($this->canRecordPayment($order) && isset(PaymentMethods::MANUAL[$order->payment_method_code])) {
            $actions[] = 'record_payment';
        }

        if ($this->canShip($order)) {
            $actions[] = 'ship';

            if ($order->fulfillment_status === FulfillmentStatus::Unfulfilled) {
                $actions[] = 'mark_packed';
            }
        }

        foreach ($this->reachableSteps($order) as $step) {
            $actions[] = 'advance:'.$step->value;
        }

        if ($order->status === OrderStatus::Pending) {
            $actions[] = 'confirm';
        }

        if ($this->canHold($order)) {
            $actions[] = 'hold';
        }

        if ($order->status === OrderStatus::OnHold) {
            $actions[] = 'release';
        }

        if ($this->canDeliver($order)) {
            $actions[] = 'mark_delivered';
        }

        if ($this->isWithCarrier($order) && $order->fulfillment_status !== FulfillmentStatus::DeliveryFailed) {
            $actions[] = 'delivery_failed';
        }

        if ($this->canReturn($order)) {
            $actions[] = 'returned';
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
            ]);
            $this->setPaidStatus($order);
            $order->save();

            $this->log($order, 'payment', "Payment of {$amount->format()} recorded ({$order->payment_method_name}).", true, $actor, ['reference' => $reference]);
            $this->audit->record('order.payment_recorded', $order, ['number' => $order->number, 'amount' => $amount->toDecimal()], $actor);

            DB::afterCommit(fn () => OrderPaid::dispatch($order->id));

            return $order;
        });
    }

    /**
     * Applies a provider-confirmed payment (verified webhook or server-side
     * retrieval). Idempotent. The amount and currency must match the payment
     * we asked for; otherwise nothing is marked paid and staff are alerted.
     * A payment that arrives after cancellation, or a second payment for an
     * already paid order, is recorded and flagged for refund.
     */
    public function confirmGatewayPayment(Payment $payment, PaymentUpdate $update): Order
    {
        return $this->transition($payment->order, function (Order $order) use ($payment, $update): Order {
            $payment = $this->lockPayment($payment);

            if (in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_MISMATCH], true)) {
                return $order;
            }

            $expected = $payment->money();

            if ($update->amount === null || ! $update->amount->equals($expected)) {
                $reported = $update->amount === null ? 'an unknown amount' : $update->amount->format();
                $payment->forceFill([
                    'status' => Payment::STATUS_MISMATCH,
                    'provider_payment_id' => $update->paymentReference,
                    'failure_reason' => "Provider reported {$reported}; expected {$expected->format()}.",
                ])->save();

                $this->log($order, 'payment', "The payment provider reported {$reported}, but {$expected->format()} was expected. The order was not marked as paid. Check the payment in the provider's dashboard.", false, null);
                $this->audit->record('payment.amount_mismatch', $order, ['number' => $order->number, 'payment_id' => $payment->id, 'expected' => $expected->toDecimal(), 'reported' => $update->amount?->toDecimal()]);

                return $order;
            }

            $payment->forceFill([
                'status' => Payment::STATUS_PAID,
                'provider_payment_id' => $update->paymentReference,
                'failure_reason' => null,
                'paid_at' => now(),
            ])->save();

            $first = $order->money('paid_total')->isZero();
            $cancelled = $order->status === OrderStatus::Cancelled;
            $order->forceFill([
                'paid_total' => $order->money('paid_total')->plus($expected)->toDecimal(),
                'paid_at' => $order->paid_at ?? now(),
            ]);
            $order->payment_status = $this->paymentStatusFor($order);

            if ($first && ! $cancelled) {
                $this->setPaidStatus($order);
            }
            $order->save();

            $this->log($order, 'payment', "Payment of {$expected->format()} received ({$order->payment_method_name}).", true, null, ['reference' => $update->paymentReference]);
            $this->audit->record('order.payment_confirmed', $order, ['number' => $order->number, 'amount' => $expected->toDecimal(), 'provider' => $payment->provider, 'reference' => $update->paymentReference]);

            if ($cancelled) {
                $this->log($order, 'payment', 'This payment arrived after the order was cancelled. Refund it to the customer.', false, null);
                $this->audit->record('order.paid_after_cancellation', $order, ['number' => $order->number, 'payment_id' => $payment->id]);
            } elseif (! $first) {
                $this->log($order, 'payment', 'A second payment was received for this order. Refund the extra payment to the customer.', false, null);
                $this->audit->record('order.duplicate_payment', $order, ['number' => $order->number, 'payment_id' => $payment->id]);
            } else {
                DB::afterCommit(fn () => OrderPaid::dispatch($order->id));
            }

            return $order;
        });
    }

    public function markGatewayPaymentProcessing(Payment $payment, PaymentUpdate $update): Order
    {
        return $this->transition($payment->order, function (Order $order) use ($payment, $update): Order {
            $payment = $this->lockPayment($payment);

            if ($payment->status === Payment::STATUS_PENDING) {
                $payment->forceFill(['status' => Payment::STATUS_PROCESSING, 'provider_payment_id' => $update->paymentReference])->save();
                $this->log($order, 'payment', 'The payment was submitted and is waiting for confirmation from the provider.', true, null);
            }

            return $order;
        });
    }

    public function failGatewayPayment(Payment $payment, string $reason): Order
    {
        return $this->transition($payment->order, function (Order $order) use ($payment, $reason): Order {
            $payment = $this->lockPayment($payment);

            if ($payment->isOpen()) {
                $payment->forceFill(['status' => Payment::STATUS_FAILED, 'failure_reason' => $reason])->save();

                if ($order->money('paid_total')->isZero() && $order->status !== OrderStatus::Cancelled) {
                    $order->forceFill(['payment_status' => PaymentStatus::Failed])->save();
                }
                $this->log($order, 'payment', "Payment failed: {$reason}", false, null);
                $this->audit->record('order.payment_failed', $order, ['number' => $order->number, 'payment_id' => $payment->id]);
            }

            return $order;
        });
    }

    /**
     * The hosted payment page expired unused. If the order has no other way
     * to be paid, it is cancelled so its stock is released.
     */
    public function expireGatewayPayment(Payment $payment): Order
    {
        return $this->transition($payment->order, function (Order $order) use ($payment): Order {
            $payment = $this->lockPayment($payment);

            if (! $payment->isOpen()) {
                return $order;
            }

            $payment->forceFill(['status' => Payment::STATUS_EXPIRED])->save();
            $this->log($order, 'payment', 'The payment page expired without a completed payment.', false, null);

            $otherActive = $order->payments()
                ->whereKeyNot($payment->id)
                ->whereIn('status', [...Payment::OPEN, Payment::STATUS_PAID, Payment::STATUS_MISMATCH])
                ->exists();

            if (! $otherActive && $order->payment_status->isOutstanding() && $this->canCancel($order)) {
                return $this->cancel($order, null, 'The payment was not completed in time.');
            }

            return $order;
        });
    }

    public function ship(Order $order, User $actor, ?string $carrier, ?string $trackingNumber, ?string $trackingUrl, bool $notify = true): Order
    {
        return $this->transition($order, function (Order $order) use ($actor, $carrier, $trackingNumber, $trackingUrl, $notify): Order {
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

            DB::afterCommit(fn () => OrderShipped::dispatch($order->id, $notify));

            return $order;
        });
    }

    public function markDelivered(Order $order, User $actor): Order
    {
        return $this->transition($order, function (Order $order) use ($actor): Order {
            if (! $this->canDeliver($order)) {
                throw ValidationException::withMessages(['order' => 'Only shipped orders can be marked as delivered.']);
            }

            $paid = ! $order->payment_status->isOutstanding();

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
                'hold_previous_status' => null,
                'cancelled_at' => now(),
            ])->save();

            // Close any payment page that is still open so it can no longer be paid.
            $open = $order->payments()->whereIn('status', Payment::OPEN)->get();
            foreach ($open as $payment) {
                $payment->forceFill(['status' => Payment::STATUS_EXPIRED, 'failure_reason' => 'Order cancelled.'])->save();
            }
            $gateway = $this->paymentMethods->gateway($order->payment_method_code);
            if ($gateway !== null && $open->isNotEmpty()) {
                DB::afterCommit(fn () => $open->each(fn (Payment $p) => $gateway->expireSession($p)));
            }

            $this->log($order, 'status', "Order cancelled: {$reason}", true, $actor);
            $this->audit->record('order.cancelled', $order, ['number' => $order->number, 'reason' => $reason], $actor);

            DB::afterCommit(fn () => OrderCancelled::dispatch($order->id));

            return $order;
        });
    }

    /**
     * Record a refund. For manual methods the money is returned outside the
     * platform; this records it for reconciliation and optionally restocks items.
     * For gateway payments the provider refund has already been issued (see
     * OrderRefunds) and `$providerRefund` describes it.
     *
     * @param  array<int, int>  $restock  order item id => quantity to put back into stock
     */
    public function refund(Order $order, User $actor, Money $amount, string $reason, array $restock = [], ?Payment $payment = null, ?RefundUpdate $providerRefund = null): Refund
    {
        return $this->transition($order, function (Order $order) use ($actor, $amount, $reason, $restock, $payment, $providerRefund): Refund {
            if (! $amount->isPositive() || $amount->currency() !== $order->currency) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount in the order currency.']);
            }

            // The provider's webhook may already have recorded this refund (without actor or restock).
            $existing = $providerRefund === null ? null : Refund::query()
                ->where('order_id', $order->id)
                ->where('provider_reference', $providerRefund->reference)
                ->lockForUpdate()
                ->first();

            // A repeated submission with the same idempotency key returns the same provider refund.
            if ($existing !== null && $existing->actor_id !== null) {
                return $existing;
            }

            if ($existing === null && $amount->greaterThan($order->refundable())) {
                throw ValidationException::withMessages(['amount' => "At most {$order->refundable()->format()} can be refunded."]);
            }

            $refund = $existing ?? new Refund;
            $refund->forceFill([
                'order_id' => $order->id,
                'payment_id' => $payment->id ?? $order->payments()->where('status', Payment::STATUS_PAID)->latest('id')->value('id'),
                'amount' => $amount->toDecimal(),
                'currency' => $order->currency,
                'reason' => $reason,
                'status' => match ($providerRefund?->status) {
                    null, RefundUpdate::SUCCEEDED => Refund::STATUS_COMPLETED,
                    default => $existing->status ?? Refund::STATUS_PENDING,
                },
                'provider_reference' => $providerRefund?->reference,
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

            if ($existing === null) {
                $order->refunded_total = $order->money('refunded_total')->plus($amount)->toDecimal();
                $order->payment_status = $this->paymentStatusFor($order);
                $order->save();

                $this->log($order, 'refund', "Refund of {$amount->format()} recorded: {$reason}", true, $actor);
                DB::afterCommit(fn () => OrderRefunded::dispatch($order->id));
            }

            $this->audit->record('order.refunded', $order, ['number' => $order->number, 'amount' => $amount->toDecimal(), 'restocked' => $restock, 'reference' => $providerRefund?->reference], $actor);

            return $refund;
        });
    }

    /**
     * Applies a refund fact reported by the provider: confirms or fails a
     * pending refund, or imports a refund made in the provider's dashboard.
     */
    public function applyProviderRefund(Payment $payment, RefundUpdate $update): Order
    {
        return $this->transition($payment->order, function (Order $order) use ($payment, $update): Order {
            if ($update->amount->currency() !== $order->currency || $update->reference === '') {
                return $order;
            }

            /** @var Refund|null $refund */
            $refund = Refund::query()->where('order_id', $order->id)->where('provider_reference', $update->reference)->lockForUpdate()->first();

            if ($refund === null) {
                if ($update->status === RefundUpdate::FAILED) {
                    return $order;
                }

                $refund = new Refund;
                $refund->forceFill([
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'amount' => $update->amount->toDecimal(),
                    'currency' => $order->currency,
                    'reason' => 'Refunded in the payment provider dashboard.',
                    'status' => $update->status === RefundUpdate::SUCCEEDED ? Refund::STATUS_COMPLETED : Refund::STATUS_PENDING,
                    'provider_reference' => $update->reference,
                ])->save();

                $order->refunded_total = $order->money('refunded_total')->plus($update->amount)->toDecimal();
                $order->payment_status = $this->paymentStatusFor($order);
                $order->save();

                $this->log($order, 'refund', "Refund of {$update->amount->format()} issued by the payment provider.", true, null, ['reference' => $update->reference]);
                $this->audit->record('order.refund_imported', $order, ['number' => $order->number, 'amount' => $update->amount->toDecimal(), 'reference' => $update->reference]);
                DB::afterCommit(fn () => OrderRefunded::dispatch($order->id));

                return $order;
            }

            if ($update->status === RefundUpdate::SUCCEEDED && $refund->status === Refund::STATUS_PENDING) {
                $refund->forceFill(['status' => Refund::STATUS_COMPLETED])->save();
                $this->log($order, 'refund', "Refund of {$refund->money()->format()} confirmed by the payment provider.", false, null);
            } elseif ($update->status === RefundUpdate::FAILED && $refund->status !== Refund::STATUS_FAILED) {
                $refund->forceFill(['status' => Refund::STATUS_FAILED, 'failure_reason' => $update->failureReason])->save();

                $order->refunded_total = $order->money('refunded_total')->minus($refund->money())->nonNegative()->toDecimal();
                $order->payment_status = $this->paymentStatusFor($order);
                $order->save();

                $reason = $update->failureReason !== null ? " ({$update->failureReason})" : '';
                $this->log($order, 'refund', "Refund of {$refund->money()->format()} failed at the payment provider{$reason}. Items restocked for it remain in stock.", false, null);
                $this->audit->record('order.refund_failed', $order, ['number' => $order->number, 'reference' => $update->reference]);
            }

            return $order;
        });
    }

    /**
     * Accept an order that was waiting for review (only when the store
     * reviews orders before confirming them).
     */
    public function confirm(Order $order, User $actor, bool $notify = false): Order
    {
        return $this->transition($order, function (Order $order) use ($actor, $notify): Order {
            if ($order->status !== OrderStatus::Pending) {
                throw ValidationException::withMessages(['order' => 'Only orders waiting for review can be confirmed.']);
            }

            $order->forceFill(['status' => OrderStatus::Confirmed])->save();

            $this->log($order, 'confirmed', 'Order confirmed.', true, $actor);
            $this->audit->record('order.confirmed', $order, ['number' => $order->number], $actor);

            if ($notify) {
                $this->notifyCustomer($order, $actor, 'Your order has been confirmed.');
            }

            return $order;
        });
    }

    /**
     * Pause an unshipped order (address check, fraud review, stock query).
     * It cannot be packed or shipped until released; payments, cancellation
     * and refunds still work. Customers keep seeing "processing".
     */
    public function hold(Order $order, User $actor, ?string $reason = null, bool $notify = false): Order
    {
        return $this->transition($order, function (Order $order) use ($actor, $reason, $notify): Order {
            if (! $this->canHold($order)) {
                throw ValidationException::withMessages(['order' => 'Only pending or processing orders that have not shipped can be put on hold.']);
            }

            $order->forceFill([
                'hold_previous_status' => $order->status->value,
                'status' => OrderStatus::OnHold,
            ])->save();

            $this->log($order, 'held', 'Order put on hold'.($reason ? ': '.rtrim($reason, '.').'.' : '.'), false, $actor, ['reason' => $reason]);
            $this->audit->record('order.held', $order, ['number' => $order->number, 'reason' => $reason], $actor);

            if ($notify) {
                $this->notifyCustomer($order, $actor, $reason ?: 'Your order is being reviewed.');
            }

            return $order;
        });
    }

    public function release(Order $order, User $actor, bool $notify = false): Order
    {
        return $this->transition($order, function (Order $order) use ($actor, $notify): Order {
            if ($order->status !== OrderStatus::OnHold) {
                throw ValidationException::withMessages(['order' => 'This order is not on hold.']);
            }

            $order->forceFill([
                'status' => OrderStatus::tryFrom((string) $order->hold_previous_status) ?? OrderStatus::PendingPayment,
                'hold_previous_status' => null,
            ])->save();

            $this->log($order, 'released', 'Hold released.', false, $actor);
            $this->audit->record('order.released', $order, ['number' => $order->number], $actor);

            if ($notify) {
                $this->notifyCustomer($order, $actor, 'Your order is being processed again.');
            }

            return $order;
        });
    }

    public function markPacked(Order $order, User $actor, bool $notify = false): Order
    {
        return $this->advanceFulfillment($order, $actor, FulfillmentStatus::Packed, $notify);
    }

    /**
     * Move the order forward to `$target` on the fulfilment path. Optional
     * steps may be skipped; steps never go back. Shipping records a shipment
     * and delivering completes paid orders.
     */
    public function advanceFulfillment(Order $order, User $actor, FulfillmentStatus $target, ?bool $notify = null, ?string $carrier = null, ?string $trackingNumber = null, ?string $trackingUrl = null): Order
    {
        if ($target === FulfillmentStatus::Shipped) {
            return $this->ship($order, $actor, $carrier, $trackingNumber, $trackingUrl, $notify ?? true);
        }

        if ($target === FulfillmentStatus::Delivered) {
            return $this->markDelivered($order, $actor);
        }

        return $this->transition($order, function (Order $order) use ($actor, $target, $notify): Order {
            if (! in_array($target, $this->reachableSteps($order), true)) {
                throw ValidationException::withMessages(['order' => "This order cannot move to \"{$target->label()}\" from its current state."]);
            }

            $warehouse = $target->isBeforeShipping();
            $order->fulfillment_status = $target;
            if ($warehouse && $order->status === OrderStatus::Confirmed) {
                $order->status = OrderStatus::Processing;
            }
            $order->save();

            $this->log($order, $target === FulfillmentStatus::Packed ? 'packed' : 'fulfillment', "Order moved to {$target->label()}.", ! $warehouse, $actor);
            $this->audit->record('order.fulfillment_advanced', $order, ['number' => $order->number, 'status' => $target->value], $actor);

            if ($notify === true) {
                $this->notifyCustomer($order, $actor, match ($target) {
                    FulfillmentStatus::InTransit => 'Your order is on its way.',
                    FulfillmentStatus::OutForDelivery => 'Your order is out for delivery today.',
                    default => 'Your order is being prepared.',
                });
            }

            return $order;
        });
    }

    /**
     * A delivery attempt failed. The carrier can try again (in transit or
     * out for delivery) or the order can be delivered or returned.
     */
    public function markDeliveryFailed(Order $order, User $actor, string $reason, bool $notify = false): Order
    {
        return $this->transition($order, function (Order $order) use ($actor, $reason, $notify): Order {
            if (! $this->isWithCarrier($order) || $order->fulfillment_status === FulfillmentStatus::DeliveryFailed) {
                throw ValidationException::withMessages(['order' => 'Only orders with the carrier can have a failed delivery.']);
            }

            $order->forceFill(['fulfillment_status' => FulfillmentStatus::DeliveryFailed])->save();

            $message = 'Delivery attempt failed: '.rtrim($reason, '.').'.';
            $this->log($order, 'fulfillment', $message, true, $actor);
            $this->audit->record('order.delivery_failed', $order, ['number' => $order->number, 'reason' => $reason], $actor);

            if ($notify) {
                $this->notifyCustomer($order, $actor, $message);
            }

            return $order;
        });
    }

    /**
     * The goods came back. Items can be put back into stock; refunds remain
     * a separate step.
     */
    public function markReturned(Order $order, User $actor, string $reason, bool $restock, bool $notify = false): Order
    {
        return $this->transition($order, function (Order $order) use ($actor, $reason, $restock, $notify): Order {
            if (! $this->canReturn($order)) {
                throw ValidationException::withMessages(['order' => 'Only shipped or delivered orders can be returned.']);
            }

            if ($restock) {
                $this->restockRemaining($order, MovementReason::Restock, $actor);
            }

            $order->forceFill(['fulfillment_status' => FulfillmentStatus::Returned])->save();

            $this->log($order, 'returned', 'Order returned: '.rtrim($reason, '.').'.'.($restock ? ' Items put back in stock.' : ''), false, $actor, ['restock' => $restock]);
            $this->audit->record('order.returned', $order, ['number' => $order->number, 'reason' => $reason, 'restock' => $restock], $actor);

            if ($notify) {
                $this->notifyCustomer($order, $actor, 'We have received your return.');
            }

            return $order;
        });
    }

    public function addNote(Order $order, User $actor, string $message, bool $customerVisible): void
    {
        $this->log($order, 'note', $message, $customerVisible, $actor);
    }

    /**
     * Payment status implied by the paid and refunded totals.
     */
    private function paymentStatusFor(Order $order): PaymentStatus
    {
        $paid = $order->money('paid_total');
        $refunded = $order->money('refunded_total');

        return match (true) {
            $paid->isZero() => PaymentStatus::Unpaid,
            $refunded->isZero() => PaymentStatus::Paid,
            ! $refunded->lessThan($paid) => PaymentStatus::Refunded,
            default => PaymentStatus::PartiallyRefunded,
        };
    }

    private function lockPayment(Payment $payment): Payment
    {
        return Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
    }

    private function canRecordPayment(Order $order): bool
    {
        return $order->payment_status->isOutstanding() && $order->status !== OrderStatus::Cancelled;
    }

    /**
     * A newly paid order moves on to processing (or completed, if it was
     * already delivered). An order on hold stays on hold and resumes as
     * processing when released.
     */
    private function setPaidStatus(Order $order): void
    {
        $delivered = $order->fulfillment_status === FulfillmentStatus::Delivered;
        $next = match (true) {
            $delivered => OrderStatus::Completed,
            $order->fulfillment_status === FulfillmentStatus::Unfulfilled => $this->reviewsOrders() ? OrderStatus::Pending : OrderStatus::Confirmed,
            default => OrderStatus::Processing, // pay-on-delivery orders already on their way
        };

        if ($order->status === OrderStatus::OnHold) {
            $order->hold_previous_status = $next->value;

            return;
        }

        $order->forceFill([
            'status' => $next,
            'completed_at' => $delivered ? now() : null,
        ]);
    }

    private function reviewsOrders(): bool
    {
        return (bool) $this->settings->get('orders.review_before_confirm', false);
    }

    private function notifyCustomer(Order $order, User $actor, string $message): void
    {
        $this->log($order, 'note', $message, true, $actor);
        DB::afterCommit(function () use ($order, $message) {
            // The status change is already saved: a mail failure must not turn it into an error.
            try {
                Mail::to($order->email)->send(new OrderMail($order, 'updated', note: $message));
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    private function canHold(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Processing], true)
            && $order->fulfillment_status->isBeforeShipping();
    }

    /**
     * Fulfilment steps the order can move to now (skipping allowed, never back).
     *
     * @return list<FulfillmentStatus>
     */
    private function reachableSteps(Order $order): array
    {
        $current = $order->fulfillment_status;

        if ($this->canShip($order)) {
            return array_values(array_filter(
                [...FulfillmentStatus::preShipSteps(), FulfillmentStatus::Shipped],
                fn (FulfillmentStatus $s) => $s->rank() > $current->rank(),
            ));
        }

        if (! $this->isWithCarrier($order)) {
            return [];
        }

        $steps = array_filter(
            [FulfillmentStatus::InTransit, FulfillmentStatus::OutForDelivery],
            fn (FulfillmentStatus $s) => $current === FulfillmentStatus::DeliveryFailed || $s->rank() > $current->rank(),
        );

        return [...array_values($steps), FulfillmentStatus::Delivered];
    }

    private function isWithCarrier(Order $order): bool
    {
        return $order->status !== OrderStatus::Cancelled && $order->fulfillment_status->isWithCarrier();
    }

    private function canDeliver(Order $order): bool
    {
        return $this->isWithCarrier($order);
    }

    private function canReturn(Order $order): bool
    {
        return $order->status !== OrderStatus::Cancelled
            && ($order->fulfillment_status->isWithCarrier() || $order->fulfillment_status === FulfillmentStatus::Delivered);
    }

    private function canShip(Order $order): bool
    {
        if (! $order->fulfillment_status->isBeforeShipping()
            || in_array($order->status, [OrderStatus::Cancelled, OrderStatus::OnHold, OrderStatus::Pending], true)) {
            return false;
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return true;
        }

        return $order->payment_status->isOutstanding()
            && ($this->paymentMethods->definition($order->payment_method_code)?->allowsFulfillmentBeforePayment() ?? false);
    }

    private function canCancel(Order $order): bool
    {
        return $order->status !== OrderStatus::Cancelled
            && $order->status !== OrderStatus::Completed
            && $order->fulfillment_status->isBeforeShipping()
            && ($order->payment_status->isOutstanding() || $order->payment_status === PaymentStatus::Refunded);
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
