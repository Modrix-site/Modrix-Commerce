<?php

namespace EcoModular\Tabby;

use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds Tabby checkout payloads (docs.tabby.ai, "Session creation payload
 * model"). Buyer and order history are sent because Tabby uses them for its
 * approval decision; only the customer's own orders at this store are used.
 */
final class TabbyPayload
{
    /** Previous orders sent as order_history (Tabby recommends 5-10). */
    private const HISTORY = 10;

    public function __construct(private readonly TabbySettings $settings) {}

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function session(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): array
    {
        $order->loadMissing(['items', 'addresses', 'user']);
        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        $phone = $order->phone ?: $shipping?->phone;

        if (! is_string($phone) || trim($phone) === '') {
            throw new GatewayException('Tabby needs the customer\'s phone number.', customerMessage: __('Tabby needs your phone number. Please add it to your order details or choose another payment method.'));
        }

        $buyer = [
            'name' => $shipping !== null ? trim("{$shipping->first_name} {$shipping->last_name}") : $order->email,
            'email' => $order->email,
            'phone' => trim($phone),
        ];

        return [
            'payment' => [
                'amount' => $payment->money()->toDisplayDecimal(),
                'currency' => $payment->currency,
                'description' => "Order {$order->number}",
                'buyer' => $buyer,
                'shipping_address' => $this->address($shipping),
                'order' => [
                    'reference_id' => (string) $order->number,
                    'updated_at' => ($order->updated_at ?? now())->toIso8601ZuluString(),
                    'tax_amount' => $this->decimal($order->money('tax_total')->plus($order->money('shipping_tax_total'))),
                    'shipping_amount' => $this->decimal($order->money('shipping_total')),
                    'discount_amount' => $this->decimal($order->money('discount_total')),
                    'items' => $this->items($order),
                ],
                'buyer_history' => $this->buyerHistory($order),
                'order_history' => $this->orderHistory($order),
                'meta' => ['order_id' => (string) $order->number, 'customer' => $order->customer_id !== null ? (string) $order->customer_id : null],
            ],
            'lang' => TabbyCopy::lang(),
            'merchant_code' => $this->settings->merchantCode(),
            'merchant_urls' => [
                'success' => $returnUrl,
                'cancel' => $cancelUrl,
                'failure' => $cancelUrl,
            ],
        ];
    }

    /**
     * The minimal payload Tabby accepts for the background pre-scoring check.
     *
     * @return array<string, mixed>
     */
    public function eligibility(Money $amount, string $email, string $phone): array
    {
        return [
            'payment' => [
                'amount' => $amount->toDisplayDecimal(),
                'currency' => $amount->currency(),
                'buyer' => ['email' => $email, 'phone' => $phone],
            ],
            'lang' => TabbyCopy::lang(),
            'merchant_code' => $this->settings->merchantCode(),
        ];
    }

    /**
     * @return array{city: string, address: string, zip: string}
     */
    private function address(?OrderAddress $address): array
    {
        return [
            'city' => (string) ($address->city ?? ''),
            'address' => trim(implode(', ', array_filter([$address?->line1, $address?->line2]))),
            // Postal codes are optional in the UAE and KSA; Tabby requires the field.
            'zip' => (string) ($address?->postal_code ?: '00000'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(Order $order): array
    {
        $categories = Product::query()->whereIn('id', $order->items->pluck('product_id')->filter())
            ->with('categories:id,name')->get(['id'])
            ->mapWithKeys(fn (Product $p) => [$p->id => (string) ($p->categories->first()->name ?? 'General')]);

        return array_values($order->items->map(fn (OrderItem $item) => [
            'reference_id' => $item->sku,
            'title' => mb_substr(trim($item->product_name.($item->variant_name ? " - {$item->variant_name}" : '')), 0, 191),
            'quantity' => max(1, (int) $item->quantity),
            'unit_price' => $this->decimal($item->money('unit_price', $order->currency)),
            'discount_amount' => $this->decimal($item->money('discount_total', $order->currency)),
            'category' => $categories[$item->product_id] ?? 'General',
        ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function buyerHistory(Order $order): array
    {
        return [
            'registered_since' => ($order->user->created_at ?? $order->placed_at ?? now())->toIso8601ZuluString(),
            'loyalty_level' => $this->previousOrders($order)->where('payment_status', PaymentStatus::Paid)->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orderHistory(Order $order): array
    {
        return array_values($this->previousOrders($order)->latest('placed_at')->limit(self::HISTORY)->get()
            ->map(fn (Order $past) => [
                'purchased_at' => $past->placed_at->toIso8601ZuluString(),
                'amount' => $this->decimal($past->money('grand_total')),
                'payment_method' => $past->payment_method_code === 'cash_on_delivery' ? 'cod' : 'card',
                'status' => match (true) {
                    $past->status === OrderStatus::Cancelled => 'canceled',
                    in_array($past->payment_status, [PaymentStatus::Refunded], true) => 'refunded',
                    $past->status === OrderStatus::Completed => 'complete',
                    $past->status === OrderStatus::Processing => 'processing',
                    default => 'new',
                },
            ])->all());
    }

    /**
     * @return Builder<Order>
     */
    private function previousOrders(Order $order)
    {
        return Order::query()->where('email', $order->email)->whereKeyNot($order->id)->where('currency', $order->currency);
    }

    private function decimal(Money $money): string
    {
        return $money->toDisplayDecimal();
    }
}
