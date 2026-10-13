<?php

namespace App\Http\Presenters;

use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\Refund;
use App\Domains\Orders\Models\Shipment;
use App\Domains\Orders\OrderDisplayStatus;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;
use Illuminate\Support\Collection;

/**
 * Shapes order snapshots for the storefront and the admin. The customer view
 * never includes internal notes, staff identities or IP addresses.
 */
final class OrderPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Order $order): array
    {
        return [
            'number' => $order->number,
            'email' => $order->email,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
            'display_status' => ($display = OrderDisplayStatus::for($order))->value,
            'display_label' => $display->label(),
            'grand_total' => $order->money('grand_total'),
            'placed_at' => $order->placed_at->toIso8601String(),
        ];
    }

    /**
     * The summary customers see. A hold is internal: the customer keeps
     * seeing the status the order had before it (pending or processing),
     * and a packed order is still "unfulfilled" until it ships.
     *
     * @return array<string, mixed>
     */
    public function customerSummary(Order $order): array
    {
        $display = OrderDisplayStatus::forCustomer($order);

        return [
            ...$this->summary($order),
            'status' => $display->value,
            'status_label' => $display->label(),
            'display_status' => $display->value,
            'display_label' => $display->label(),
            // Warehouse steps are internal: still "unfulfilled" until it ships.
            'fulfillment_status' => $order->fulfillment_status->isBeforeShipping() ? FulfillmentStatus::Unfulfilled->value : $order->fulfillment_status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forCustomer(Order $order): array
    {
        $order->loadMissing('items', 'addresses', 'shipments', 'payments', 'invoice');

        return [
            ...$this->customerSummary($order),
            ...$this->money($order),
            'payment_method' => $order->payment_method_name,
            'shipping_method' => $order->shipping_method_name,
            'coupon_code' => $order->coupon_code,
            'customer_note' => $order->customer_note,
            'items' => $order->items->map(fn (OrderItem $i) => $this->item($i, $order->currency))->values(),
            'addresses' => $order->addresses->mapWithKeys(fn (OrderAddress $a) => [$a->type => $a->toPresentable()]),
            'shipments' => $order->shipments->map(fn (Shipment $s) => [
                'carrier' => $s->carrier,
                'tracking_number' => $s->tracking_number,
                'tracking_url' => $s->tracking_url,
                'shipped_at' => $s->shipped_at->toIso8601String(),
                'delivered_at' => $s->delivered_at?->toIso8601String(),
            ])->values(),
            'history' => $order->events()->where('is_customer_visible', true)->get()
                ->map(fn (OrderEvent $e) => ['message' => $e->message, 'created_at' => $e->created_at->toIso8601String()])
                ->values(),
            'documents' => [
                'invoice' => $order->invoice ? ['number' => $order->invoice->number, 'url' => route('account.orders.invoice', $order)] : null,
                'receipts' => $this->receipts($order, fn (Payment $p) => route('account.orders.receipt', [$order, $p])),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forAdmin(Order $order): array
    {
        $order->loadMissing('items', 'addresses', 'shipments', 'payments.recorder:id,name', 'refunds.actor:id,name', 'customer', 'invoice');
        $events = $order->events()->with('actor:id,email')->get();

        return [
            ...$this->forCustomer($order),
            // Staff see the real statuses (customers never see holds or packing).
            ...$this->summary($order),
            'id' => $order->id,
            'phone' => $order->phone,
            'ip_address' => $order->ip_address,
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->fullName(),
                'registered' => $order->customer->user_id !== null,
            ] : null,
            'paid_total' => $order->money('paid_total'),
            'refunded_total' => $order->money('refunded_total'),
            'refundable' => $order->refundable(),
            'balance_due' => $order->balanceDue(),
            'payment_method_code' => $order->payment_method_code,
            'payments' => $order->payments->sortByDesc(fn (Payment $p) => [$p->paid_at ?? $p->created_at, $p->id])->map(fn (Payment $p) => [
                'id' => $p->id,
                'method' => $p->method_code,
                'method_label' => OrderWorkflow::MANUAL_METHODS[$p->method_code] ?? ($p->method_code === $order->payment_method_code ? $order->payment_method_name : ucfirst(str_replace('_', ' ', $p->method_code))),
                'recorded_by' => $p->recorder?->name,
                'note' => $p->note,
                'receipt_url' => $p->status === Payment::STATUS_PAID ? route('admin.orders.documents.receipt', [$order, $p]) : null,
                'provider' => $p->provider,
                'reference' => $p->provider_payment_id ?? $p->provider_reference,
                'amount' => Money::of($p->amount, $order->currency),
                'status' => $p->status,
                'failure_reason' => $p->failure_reason,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
            ])->values(),
            'refunds' => $order->refunds->map(fn (Refund $r) => [
                'id' => $r->id,
                'recorded_by' => $r->actor?->name,
                'amount' => Money::of($r->amount, $order->currency),
                'reason' => $r->reason,
                'status' => $r->status,
                'reference' => $r->provider_reference,
                'failure_reason' => $r->failure_reason,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values(),
            'history' => $events->map(fn (OrderEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'message' => $e->message,
                'customer_visible' => $e->is_customer_visible,
                'actor' => $e->actor?->email,
                'created_at' => $e->created_at->toIso8601String(),
            ])->values(),
            'timeline' => $events->sortByDesc(fn (OrderEvent $e) => [$e->created_at->getTimestamp(), $e->id])->map(fn (OrderEvent $e) => [
                'id' => $e->id,
                'kind' => $e->type,
                'message' => $e->message,
                'customer_visible' => $e->is_customer_visible,
                'actor' => $e->actor?->email,
                'at' => $e->created_at->toIso8601String(),
            ])->values(),
            'steps' => $this->steps($order, $events),
            'documents' => [
                'invoice' => $order->invoice ? [
                    'number' => $order->invoice->number,
                    'issued_at' => $order->invoice->issued_at->toIso8601String(),
                    'url' => route('admin.orders.documents.invoice', $order),
                ] : null,
                'receipts' => $this->receipts($order, fn (Payment $p) => route('admin.orders.documents.receipt', [$order, $p])),
                'packing_slip_url' => route('admin.orders.documents.packing-slip', $order),
            ],
        ];
    }

    /**
     * One receipt per successful payment.
     *
     * @param  callable(Payment): string  $url
     * @return list<array{id: int, label: string, amount: Money, paid_at: string|null, url: string}>
     */
    private function receipts(Order $order, callable $url): array
    {
        $receipts = [];

        foreach ($order->payments as $p) {
            if ($p->status === Payment::STATUS_PAID) {
                $receipts[] = [
                    'id' => $p->id,
                    'label' => 'Receipt',
                    'amount' => Money::of($p->amount, $order->currency),
                    'paid_at' => $p->paid_at?->toIso8601String(),
                    'url' => $url($p),
                ];
            }
        }

        return $receipts;
    }

    /**
     * Progress through placed → paid → packed → shipped → delivered.
     *
     * @param  Collection<int, OrderEvent>  $events
     * @return list<array{key: string, label: string, done: bool, at: string|null}>
     */
    private function steps(Order $order, Collection $events): array
    {
        // When each fulfilment step was reached, from the order's history.
        $reachedAt = [];
        foreach ($events as $event) {
            $status = $event->metadata['status'] ?? ($event->type === 'packed' ? FulfillmentStatus::Packed->value : null);
            if (is_string($status)) {
                $reachedAt[$status] = $event->created_at;
            }
        }
        if ($order->shipped_at) {
            $reachedAt[FulfillmentStatus::Shipped->value] = $order->shipped_at;
        }
        if ($order->delivered_at) {
            $reachedAt[FulfillmentStatus::Delivered->value] = $order->delivered_at;
        }

        // The furthest point on the main path (side states count by evidence only).
        $current = $order->fulfillment_status;
        $reached = in_array($current, [FulfillmentStatus::DeliveryFailed, FulfillmentStatus::Returned], true) ? 0 : $current->rank();
        foreach (array_keys($reachedAt) as $value) {
            $reached = max($reached, FulfillmentStatus::from($value)->rank());
        }

        $effective = $order->status === OrderStatus::OnHold ? OrderStatus::tryFrom((string) $order->hold_previous_status) : $order->status;
        $confirmed = ! in_array($effective, [OrderStatus::PendingPayment, OrderStatus::Pending, null], true);
        $confirmedAt = $events->firstWhere('type', 'confirmed')->created_at ?? $order->paid_at ?? $order->placed_at;

        $steps = [
            ['key' => 'placed', 'label' => 'Placed', 'done' => true, 'skipped' => false, 'at' => $order->placed_at->toIso8601String()],
            ['key' => 'paid', 'label' => 'Paid', 'done' => $order->paid_at !== null, 'skipped' => false, 'at' => $order->paid_at?->toIso8601String()],
            ['key' => 'confirmed', 'label' => 'Confirmed', 'done' => $confirmed, 'skipped' => false, 'at' => $confirmed ? $confirmedAt->toIso8601String() : null],
        ];

        foreach ([...FulfillmentStatus::preShipSteps(), FulfillmentStatus::Shipped, FulfillmentStatus::InTransit, FulfillmentStatus::OutForDelivery, FulfillmentStatus::Delivered] as $step) {
            $done = $step->rank() <= $reached;
            $at = $reachedAt[$step->value] ?? null;
            $steps[] = ['key' => $step->value, 'label' => $step->label(), 'done' => $done, 'skipped' => $done && $at === null, 'at' => $at?->toIso8601String()];
        }

        return $steps;
    }

    /**
     * @return array<string, mixed>
     */
    private function money(Order $order): array
    {
        return [
            'currency' => $order->currency,
            'subtotal' => $order->money('subtotal'),
            'discount_total' => $order->money('discount_total'),
            'shipping_total' => $order->money('shipping_total'),
            'tax_total' => $order->money('tax_total'),
            'grand_total' => $order->money('grand_total'),
            'prices_include_tax' => $order->prices_include_tax,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(OrderItem $item, string $currency): array
    {
        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'quantity' => $item->quantity,
            'restocked_quantity' => $item->restocked_quantity,
            'unit_price' => $item->money('unit_price', $currency),
            'subtotal' => $item->money('subtotal', $currency),
            'discount' => $item->money('discount_total', $currency),
            'tax' => $item->money('tax_total', $currency),
            'total' => $item->money('total', $currency),
        ];
    }
}
