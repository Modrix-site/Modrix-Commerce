<?php

namespace App\Domains\Orders\Documents;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Models\Invoice;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Orders\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Invoices, payment receipts and packing slips. Invoices are stored
 * snapshots; receipts and packing slips are rendered from records that do
 * not change (payments, refunds, order items).
 */
final class OrderDocuments
{
    public function __construct(
        private readonly InvoiceNumbers $numbers,
        private readonly DocumentSettings $settings,
        private readonly StoreSettings $store,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Issues the order's invoice, or returns the one already issued.
     */
    public function issueInvoice(Order $order, ?User $actor = null): Invoice
    {
        if ($existing = Invoice::query()->where('order_id', $order->id)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($order, $actor): Invoice {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($existing = Invoice::query()->where('order_id', $locked->id)->first()) {
                return $existing;
            }

            $locked->load('items', 'addresses');
            $settings = $this->settings->toArray();
            $store = $this->store->toArray();
            ['sequence' => $sequence, 'number' => $number] = $this->numbers->take();

            /** @var OrderAddress|null $billing */
            $billing = $locked->addresses->firstWhere('type', 'billing') ?? $locked->addresses->firstWhere('type', 'shipping');
            $name = $billing ? trim($billing->first_name.' '.$billing->last_name) : '';

            $invoice = new Invoice;
            $invoice->forceFill([
                'order_id' => $locked->id,
                'number' => $number,
                'sequence' => $sequence,
                'issued_at' => now(),
                'currency' => $locked->currency,
                'seller' => [
                    'name' => $store['store_name'],
                    'email' => $store['store_email'],
                    'address' => $settings['seller_address'],
                    'tax_id' => $settings['tax_id'],
                ],
                'buyer' => [
                    'name' => $name !== '' ? $name : $locked->email,
                    'email' => $locked->email,
                    'address' => $billing?->toPresentable(),
                ],
                'lines' => $locked->items->map(fn (OrderItem $item) => [
                    'name' => $item->product_name,
                    'variant' => $item->variant_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'tax' => $item->tax_total,
                    'discount' => $item->discount_total,
                    'total' => $item->total,
                ])->values()->all(),
                'totals' => [
                    'subtotal' => $locked->subtotal,
                    'discount_total' => $locked->discount_total,
                    'shipping_total' => $locked->shipping_total,
                    'tax_total' => $locked->tax_total,
                    'grand_total' => $locked->grand_total,
                    'prices_include_tax' => $locked->prices_include_tax,
                    'shipping_method' => $locked->shipping_method_name,
                    'payment_method' => $locked->payment_method_name,
                ],
                'notes' => $settings['footer'],
                'issued_by' => $actor?->id,
            ])->save();

            OrderEvent::query()->forceCreate([
                'order_id' => $locked->id,
                'type' => 'invoice_issued',
                'message' => "Invoice {$number} issued.",
                'is_customer_visible' => false,
                'actor_id' => $actor?->id,
            ]);
            $this->audit->record('orders.invoice_issued', $locked, ['number' => $locked->number, 'invoice' => $number], $actor);

            return $invoice;
        });
    }
}
