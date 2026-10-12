<?php

namespace App\Http\Presenters;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\Refund;
use App\Domains\Orders\Models\Shipment;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;

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
            'grand_total' => $order->money('grand_total'),
            'placed_at' => $order->placed_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forCustomer(Order $order): array
    {
        $order->loadMissing('items', 'addresses', 'shipments');

        return [
            ...$this->summary($order),
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forAdmin(Order $order): array
    {
        $order->loadMissing('items', 'addresses', 'shipments', 'payments', 'refunds', 'customer');

        return [
            ...$this->forCustomer($order),
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
            'payments' => $order->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'method' => $p->method_code,
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
                'amount' => Money::of($r->amount, $order->currency),
                'reason' => $r->reason,
                'status' => $r->status,
                'reference' => $r->provider_reference,
                'failure_reason' => $r->failure_reason,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values(),
            'history' => $order->events()->with('actor:id,email')->get()->map(fn (OrderEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'message' => $e->message,
                'customer_visible' => $e->is_customer_visible,
                'actor' => $e->actor?->email,
                'created_at' => $e->created_at->toIso8601String(),
            ])->values(),
        ];
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
