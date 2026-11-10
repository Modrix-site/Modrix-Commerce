<?php

namespace EcoModular\Tamara;

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
 * Builds Tamara checkout payloads (docs.tamara.co, "Create checkout session").
 * The consumer and order history are sent because Tamara uses them for its
 * approval decision; only the customer's own orders at this store are used.
 */
final class TamaraPayload
{
    /** Previous orders sent to Tamara (it recommends 5-10). */
    private const HISTORY = 10;

    public function __construct(private readonly TamaraSettings $settings) {}

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function session(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): array
    {
        $order->loadMissing(['items', 'addresses', 'user']);
        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        $billing = $order->addresses->firstWhere('type', 'billing') ?? $shipping;
        $phone = $order->phone ?: $shipping?->phone;

        if (! is_string($phone) || trim($phone) === '') {
            throw new GatewayException('Tamara needs the customer\'s phone number.', customerMessage: __('Tamara needs your phone number. Please add it to your order details or choose another payment method.'));
        }

        $consumer = $this->consumer($order, $shipping, trim($phone));
        $country = $this->settings->market()?->country() ?? 'AE';

        return [
            'order' => [
                'reference_id' => (string) $order->number,
                'currency' => $payment->currency,
                'amount' => $payment->money()->toDisplayDecimal(),
                'tax_amount' => $this->decimal($order->money('tax_total')->plus($order->money('shipping_tax_total'))),
                'shipping_amount' => $this->decimal($order->money('shipping_total')),
                'discount_amount' => $this->decimal($order->money('discount_total')),
                'items' => $this->items($order),
                'consumer' => $consumer,
                'shipping_address' => $this->address($shipping, $consumer, $country),
                'billing_address' => $this->address($billing, $consumer, $country),
                'country_code' => $country,
                'locale' => TamaraCopy::locale(null, $country),
                'description' => "Order {$order->number}",
                'additional_data' => [
                    'order_id' => (string) $order->number,
                    'customer_id' => $order->customer_id !== null ? (string) $order->customer_id : null,
                    'buyer_history' => $this->buyerHistory($order),
                    'order_history' => $this->orderHistory($order),
                ],
            ],
            'payment_type' => 'PAY_BY_INSTALMENTS',
            'instalments' => 4,
            'is_mobile' => false,
            'locale' => TamaraCopy::locale(null, $country),
            'merchant_urls' => [
                'success' => $returnUrl,
                'failure' => $cancelUrl,
                'cancel' => $cancelUrl,
                // Tamara delivers every payment change here; the platform verifies the token.
                'notification' => route('webhooks.payments', TamaraGateway::CODE),
            ],
        ];
    }

    /**
     * @return array{first_name: string, last_name: string, phone_number: string, email: string}
     */
    private function consumer(Order $order, ?OrderAddress $shipping, string $phone): array
    {
        $first = $shipping?->first_name ?: 'Customer';
        $last = $shipping?->last_name ?: '';

        return [
            'first_name' => mb_substr($first, 0, 50),
            'last_name' => mb_substr($last, 0, 50),
            'phone_number' => mb_substr($phone, 0, 30),
            'email' => mb_substr($order->email, 0, 191),
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
            'name' => mb_substr(trim($item->product_name.($item->variant_name ? " - {$item->variant_name}" : '')), 0, 191),
            'sku' => $item->sku,
            'quantity' => max(1, (int) $item->quantity),
            'unit_price' => $this->decimal($item->money('unit_price', $order->currency)),
            'total_amount' => $this->decimal($item->money('subtotal', $order->currency)),
            'tax_amount' => '0.00',
            'discount_amount' => $this->decimal($item->money('discount_total', $order->currency)),
            'type' => 'Physical',
            'category' => $categories[$item->product_id] ?? 'General',
        ])->all());
    }

    /**
     * @param  array{first_name: string, last_name: string, phone_number: string, email: string}  $consumer
     * @return array<string, string>
     */
    private function address(?OrderAddress $address, array $consumer, string $country): array
    {
        $line1 = $address === null ? '' : $address->line1;
        $line2 = $address === null ? '' : $address->line2;
        $city = $address === null ? '' : $address->city;
        $postal = $address === null ? '' : $address->postal_code;
        $addressCountry = $address === null ? '' : $address->country_code;

        return [
            'first_name' => $consumer['first_name'],
            'last_name' => $consumer['last_name'],
            'line1' => mb_substr((string) $line1, 0, 191),
            'line2' => mb_substr((string) $line2, 0, 191),
            'city' => mb_substr((string) $city, 0, 100),
            // Postal codes are optional in the GCC; Tamara requires the field.
            'postal_code' => mb_substr((string) ($postal ?: '00000'), 0, 20),
            'country_code' => strtoupper((string) ($addressCountry ?: $country)),
            'phone_number' => $consumer['phone_number'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buyerHistory(Order $order): array
    {
        return [
            'registered_since' => ($order->user->created_at ?? $order->placed_at ?? now())->toIso8601ZuluString(),
            'loyalty_level' => $this->previousOrders($order)->where('payment_status', PaymentStatus::Paid)->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function orderHistory(Order $order): array
    {
        return array_values($this->previousOrders($order)->latest('placed_at')->limit(self::HISTORY)->get()
            ->map(fn (Order $past) => [
                'purchased_at' => $past->placed_at->toIso8601ZuluString(),
                'amount' => $this->decimal($past->money('grand_total')),
                'payment_method' => $past->payment_method_code === 'cash_on_delivery' ? 'cod' : 'card',
                'status' => match (true) {
                    $past->status === OrderStatus::Cancelled => 'canceled',
                    $past->payment_status === PaymentStatus::Refunded => 'refunded',
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
