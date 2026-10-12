<?php

namespace App\Domains\Checkout;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\SettingsRepository;
use App\Domains\Cart\CartManager;
use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Customers\CustomerProfiles;
use App\Domains\Customers\Models\Customer;
use App\Domains\Discounts\Models\Coupon;
use App\Domains\Discounts\Models\CouponRedemption;
use App\Domains\Inventory\Exceptions\InsufficientStock;
use App\Domains\Inventory\InventoryService;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a cart into an order in a single transaction:
 *
 *  1. lock the cart (and short-circuit a repeated submission by idempotency key);
 *  2. reprice everything on the server for the chosen destination and method;
 *  3. refuse if anything is unavailable, the coupon no longer applies, or the
 *     total differs from what the shopper saw;
 *  4. allocate stock under row locks (all-or-nothing);
 *  5. snapshot items, money and addresses; redeem the coupon under a lock;
 *  6. empty the cart; dispatch OrderPlaced after commit.
 *
 * The order is left unpaid: payment is confirmed separately (manually or, in
 * Phase 3, by a verified gateway webhook).
 */
final class PlaceOrder
{
    public function __construct(
        private readonly CartPricer $pricer,
        private readonly InventoryService $inventory,
        private readonly PaymentMethods $paymentMethods,
        private readonly CustomerProfiles $customers,
        private readonly CartManager $carts,
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Cart $cart, CheckoutData $data, ?User $user, ?string $ipAddress = null): Order
    {
        $existing = Order::query()->where('idempotency_key', $data->idempotencyKey)->first();

        if ($existing !== null) {
            return $this->ownedBy($existing, $user, $data);
        }

        if ($user === null && ! $this->guestCheckoutEnabled()) {
            throw ValidationException::withMessages(['email' => 'Please sign in or create an account to check out.']);
        }

        return DB::transaction(function () use ($cart, $data, $user, $ipAddress) {
            /** @var Cart $cart */
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

            // A concurrent request with the same key may have won the race.
            $existing = Order::query()->where('idempotency_key', $data->idempotencyKey)->first();

            if ($existing !== null) {
                return $this->ownedBy($existing, $user, $data);
            }

            $customer = $user !== null
                ? $this->customers->forUser($user)
                : $this->customers->guest($data->email, $data->shippingAddress['first_name'] ?? null, $data->shippingAddress['last_name'] ?? null, $data->phone);

            $priced = $this->pricer->price($cart, new PricingContext(
                customerId: $user !== null ? $customer->id : null,
                email: $data->email,
                countryCode: $data->shippingAddress['country_code'] ?? null,
                region: $data->shippingAddress['region'] ?? null,
                postalCode: $data->shippingAddress['postal_code'] ?? null,
                shippingMethod: $data->shippingMethod,
            ));

            $this->assertCheckoutable($priced, $data);
            $paymentMethod = $this->paymentMethods->availableFor($priced)[$data->paymentMethod]
                ?? throw ValidationException::withMessages(['payment_method' => 'Choose an available payment method.']);

            $order = $this->createOrder($priced, $data, $customer, $user, $paymentMethod->code(), $paymentMethod->label(), $ipAddress);

            try {
                $quantities = [];
                foreach ($priced->purchasableLines() as $line) {
                    $quantities[$line->variant->id] = ($quantities[$line->variant->id] ?? 0) + $line->quantity;
                }
                $this->inventory->allocate($quantities, $order, $user);
            } catch (InsufficientStock $e) {
                $skus = implode(', ', array_column($e->shortages, 'sku'));
                throw ValidationException::withMessages(['cart' => "Some items just sold out ({$skus}). Please review your cart."]);
            }

            $this->redeemCoupon($priced, $order, $customer, $data->email);

            OrderEvent::query()->forceCreate([
                'order_id' => $order->id,
                'type' => 'status',
                'message' => "Order placed. Payment method: {$paymentMethod->label()}.",
                'is_customer_visible' => true,
            ]);

            if ($data->saveAddress && $user !== null && ! $customer->addresses()->exists()) {
                $this->customers->saveAddress($customer, $data->shippingAddress);
            }

            $this->carts->empty($cart);
            $this->audit->record('order.placed', $order, ['number' => $order->number, 'total' => $order->grand_total], $user);

            DB::afterCommit(fn () => OrderPlaced::dispatch($order->id));

            return $order;
        });
    }

    public function guestCheckoutEnabled(): bool
    {
        return (bool) $this->settings->get('checkout.guest_enabled', true);
    }

    private function assertCheckoutable(PricedCart $priced, CheckoutData $data): void
    {
        if ($priced->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        if (! $priced->canCheckout()) {
            throw ValidationException::withMessages(['cart' => $priced->issues[0] ?? 'Some items in your cart are no longer available. Please review your cart.']);
        }

        if ($priced->couponCode !== null && $priced->couponError !== null) {
            throw ValidationException::withMessages(['coupon' => $priced->couponError]);
        }

        if ($priced->shipping === null) {
            throw ValidationException::withMessages(['shipping_method' => 'Choose a shipping method available for your address.']);
        }

        if ($data->expectedTotal !== null) {
            $expected = Money::of($data->expectedTotal, $priced->currency);

            if (! $expected->equals($priced->grandTotal())) {
                throw ValidationException::withMessages([
                    'total' => "The total changed to {$priced->grandTotal()->format()}. Please review your order and submit again.",
                ]);
            }
        }
    }

    private function createOrder(PricedCart $priced, CheckoutData $data, Customer $customer, ?User $user, string $paymentCode, string $paymentLabel, ?string $ipAddress): Order
    {
        $order = new Order;
        $order->forceFill([
            'customer_id' => $customer->id,
            'user_id' => $user?->id,
            'email' => Str::lower($data->email),
            'phone' => $data->phone,
            'currency' => $priced->currency,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal' => $priced->subtotal()->toDecimal(),
            'discount_total' => $priced->discountTotal->toDecimal(),
            'shipping_total' => $priced->shippingTotal->toDecimal(),
            'shipping_tax_total' => $priced->shippingTax->toDecimal(),
            'tax_total' => $priced->taxTotal->toDecimal(),
            'grand_total' => $priced->grandTotal()->toDecimal(),
            'prices_include_tax' => $priced->pricesIncludeTax,
            'coupon_code' => $priced->discountTotal->isPositive() ? $priced->couponCode : null,
            'shipping_method_code' => $priced->shipping['code'] ?? null,
            'shipping_method_name' => $priced->shipping['label'] ?? null,
            'payment_method_code' => $paymentCode,
            'payment_method_name' => $paymentLabel,
            'customer_note' => $data->customerNote,
            'idempotency_key' => $data->idempotencyKey,
            'ip_address' => $ipAddress,
            'placed_at' => now(),
        ])->save();

        $prefix = (string) config('ecomodular.order_number_prefix', 'EM-');
        $order->forceFill(['number' => $prefix.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)])->save();

        foreach ($priced->purchasableLines() as $line) {
            $subtotal = $line->subtotal();
            $total = $subtotal->minus($line->discount);

            if (! $priced->pricesIncludeTax) {
                $total = $total->plus($line->tax);
            }

            OrderItem::query()->forceCreate([
                'order_id' => $order->id,
                'product_id' => $line->variant->product_id,
                'variant_id' => $line->variant->id,
                'sku' => $line->variant->sku,
                'product_name' => $line->variant->product->name,
                'variant_name' => $line->variant->name,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice?->toDecimal() ?? '0',
                'subtotal' => $subtotal->toDecimal(),
                'discount_total' => $line->discount->toDecimal(),
                'tax_rate' => $line->taxRate,
                'tax_total' => $line->tax->toDecimal(),
                'total' => $total->toDecimal(),
            ]);
        }

        $this->snapshotAddress($order, OrderAddress::SHIPPING, $data->shippingAddress);
        $this->snapshotAddress($order, OrderAddress::BILLING, $data->billingAddress ?? $data->shippingAddress);

        return $order;
    }

    /**
     * @param  array<string, string|null>  $address
     */
    private function snapshotAddress(Order $order, string $type, array $address): void
    {
        OrderAddress::query()->forceCreate([
            'order_id' => $order->id,
            'type' => $type,
            'first_name' => (string) ($address['first_name'] ?? ''),
            'last_name' => (string) ($address['last_name'] ?? ''),
            'company' => $address['company'] ?? null,
            'line1' => (string) ($address['line1'] ?? ''),
            'line2' => $address['line2'] ?? null,
            'city' => (string) ($address['city'] ?? ''),
            'region' => $address['region'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country_code' => strtoupper((string) ($address['country_code'] ?? '')),
            'phone' => $address['phone'] ?? null,
        ]);
    }

    private function redeemCoupon(PricedCart $priced, Order $order, Customer $customer, string $email): void
    {
        if ($order->coupon_code === null || ! $priced->discountTotal->isPositive()) {
            return;
        }

        /** @var Coupon $coupon */
        $coupon = Coupon::query()->where('code', $order->coupon_code)->lockForUpdate()->firstOrFail();

        // Re-checked under the lock so concurrent checkouts cannot exceed the limit.
        if ($coupon->usage_limit !== null && $coupon->times_used >= $coupon->usage_limit) {
            throw ValidationException::withMessages(['coupon' => 'This coupon has just reached its usage limit.']);
        }

        $coupon->increment('times_used');

        $redemption = new CouponRedemption;
        $redemption->forceFill([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'email' => Str::lower($email),
            'amount' => $priced->discountTotal->toDecimal(),
            'currency' => $priced->currency,
        ])->save();
    }

    /**
     * A repeated submission returns the original order, but only to its owner.
     */
    private function ownedBy(Order $order, ?User $user, CheckoutData $data): Order
    {
        $sameOwner = $user !== null ? $order->user_id === $user->id : ($order->user_id === null && $order->email === Str::lower($data->email));

        if (! $sameOwner) {
            throw ValidationException::withMessages(['cart' => 'This checkout session has expired. Please reload the page.']);
        }

        return $order;
    }
}
