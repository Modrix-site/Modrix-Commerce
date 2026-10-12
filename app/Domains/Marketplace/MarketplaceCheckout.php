<?php

namespace App\Domains\Marketplace;

use App\Core\Audit\AuditLogger;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\MarketplaceOrder;
use App\Domains\Marketplace\Models\MarketplaceOrderItem;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Gateways\Stripe\StripeAmount;
use App\Domains\Payments\Gateways\Stripe\StripeApi;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchases on the marketplace, paid through the marketplace's own Stripe
 * account (MARKETPLACE_STRIPE_*), never through a merchant store's payment
 * setup, and recorded in marketplace_orders, never in store orders.
 *
 * As in the store (ADR-027), an order becomes paid only from a verified
 * webhook or a server-side session lookup, with the amount checked; then a
 * license is issued per item. Fulfilment is idempotent.
 */
final class MarketplaceCheckout
{
    public function __construct(
        private readonly LicenseManager $licenses,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  callable(MarketplaceOrder): array{0: string, 1: string}  $urls  success and cancel URLs for the order
     * @return array{order: MarketplaceOrder, redirect: string|null} redirect is the payment page, or null when free
     *
     * @throws ValidationException|GatewayException
     */
    public function start(User $buyer, MarketplaceProduct $product, callable $urls): array
    {
        if ($product->status !== MarketplaceProduct::PUBLISHED || $product->latestPublishedRelease() === null) {
            throw ValidationException::withMessages(['product' => 'This product is not available for purchase.']);
        }

        $owned = License::query()->where('user_id', $buyer->id)->where('product_id', $product->id)->where('status', License::ACTIVE)->exists();
        if ($owned) {
            throw ValidationException::withMessages(['product' => 'You already have an active license for this product.']);
        }

        $order = DB::transaction(function () use ($buyer, $product) {
            $order = new MarketplaceOrder;
            $order->forceFill([
                'user_id' => $buyer->id,
                'email' => $buyer->email,
                'status' => MarketplaceOrder::PENDING,
                'currency' => $product->currency,
                'total' => $product->money()->toDecimal(),
            ])->save();
            $order->forceFill(['number' => 'MP-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)])->save();

            $item = new MarketplaceOrderItem;
            $item->forceFill([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_type' => $product->type,
                'unit_price' => $product->money()->toDecimal(),
            ])->save();

            return $order;
        });

        if ($product->isFree()) {
            $this->fulfil($order, Money::zero($order->currency), null);

            return ['order' => $order->fresh() ?? $order, 'redirect' => null];
        }

        [$successUrl, $cancelUrl] = $urls($order);
        $session = StripeApi::request($this->secret(), 'post', 'v1/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $order->number,
            'customer_email' => $buyer->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => StripeAmount::toMinor($order->money()),
                    'product_data' => ['name' => $product->name.' (license)'],
                ],
            ]],
            'metadata' => ['marketplace_order' => (string) $order->number],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], "ecomodular-marketplace-order-{$order->id}");

        $order->forceFill(['stripe_session_id' => (string) ($session['id'] ?? '')])->save();

        return ['order' => $order, 'redirect' => (string) ($session['url'] ?? '')];
    }

    /**
     * Asks Stripe about the order's session (customer returned) and fulfils it if paid.
     */
    public function reconcile(MarketplaceOrder $order): MarketplaceOrder
    {
        if ($order->status !== MarketplaceOrder::PENDING || $order->stripe_session_id === null) {
            return $order;
        }

        $session = StripeApi::request($this->secret(), 'get', 'v1/checkout/sessions/'.rawurlencode($order->stripe_session_id));
        $this->applySession($session);

        return $order->fresh() ?? $order;
    }

    /**
     * Applies a Stripe Checkout session object (from a verified webhook or a lookup).
     *
     * @param  array<string, mixed>  $session
     */
    public function applySession(array $session): void
    {
        $order = MarketplaceOrder::query()->where('stripe_session_id', (string) ($session['id'] ?? ''))->first();

        if ($order === null || ($session['status'] ?? null) !== 'complete' || ($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $paid = is_int($session['amount_total'] ?? null) && is_string($session['currency'] ?? null)
            ? StripeAmount::toMoney($session['amount_total'], $session['currency'])
            : null;
        $intent = is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null;

        $this->fulfil($order, $paid, $intent);
    }

    /**
     * Marks the order paid and issues licenses. Idempotent; refuses amounts
     * that do not match the order.
     */
    public function fulfil(MarketplaceOrder $order, ?Money $paid, ?string $paymentIntent): void
    {
        DB::transaction(function () use ($order, $paid, $paymentIntent) {
            /** @var MarketplaceOrder $locked */
            $locked = MarketplaceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== MarketplaceOrder::PENDING) {
                return;
            }

            if ($paid === null || ! $paid->equals($locked->money())) {
                $this->audit->record('marketplace.payment_mismatch', null, ['order' => $locked->number, 'expected' => $locked->total, 'reported' => $paid?->toDecimal()]);

                return;
            }

            $locked->forceFill(['status' => MarketplaceOrder::PAID, 'paid_at' => now(), 'stripe_payment_intent' => $paymentIntent])->save();

            foreach ($locked->items()->with('product')->get() as $item) {
                if ($item->license_id === null) {
                    $this->licenses->issue($item->product, $locked->user, $item);
                }
            }

            $this->audit->record('marketplace.order_paid', null, ['order' => $locked->number, 'total' => $locked->total]);
        });
    }

    /**
     * Refunds a paid order in full at Stripe (free orders are only revoked)
     * and moves its licenses to "refunded": downloads, updates and new
     * activations stop immediately.
     *
     * @throws ValidationException
     */
    public function refund(MarketplaceOrder $order, string $reason, ?User $actor): MarketplaceOrder
    {
        if ($order->status !== MarketplaceOrder::PAID) {
            throw ValidationException::withMessages(['order' => 'Only paid orders can be refunded.']);
        }

        if ($order->money()->isPositive()) {
            if ($order->stripe_payment_intent === null) {
                throw ValidationException::withMessages(['order' => 'This order has no Stripe payment to refund.']);
            }

            try {
                StripeApi::request($this->secret(), 'post', 'v1/refunds', [
                    'payment_intent' => $order->stripe_payment_intent,
                    'metadata' => ['marketplace_order' => (string) $order->number],
                ], "ecomodular-marketplace-refund-{$order->id}");
            } catch (GatewayException $e) {
                throw ValidationException::withMessages(['order' => $e->getMessage()]);
            }
        }

        $this->markRefunded($order, $reason, $actor);

        return $order->fresh() ?? $order;
    }

    /**
     * Records a refund (from the admin or from a Stripe refund webhook).
     */
    public function markRefunded(MarketplaceOrder $order, string $reason, ?User $actor): void
    {
        DB::transaction(function () use ($order, $reason, $actor) {
            /** @var MarketplaceOrder $locked */
            $locked = MarketplaceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === MarketplaceOrder::REFUNDED) {
                return;
            }

            $locked->forceFill(['status' => MarketplaceOrder::REFUNDED, 'refunded_at' => now()])->save();

            foreach ($locked->items()->get() as $item) {
                $license = $item->license_id !== null ? License::query()->find($item->license_id) : null;

                if ($license !== null && $license->status !== License::REFUNDED) {
                    $this->licenses->changeStatus($license, License::REFUNDED, $reason, $actor);
                }
            }

            $this->audit->record('marketplace.order_refunded', null, ['order' => $locked->number, 'reason' => $reason], $actor);
        });
    }

    /**
     * Handles a refund reported by Stripe: a full refund of the payment refunds the order.
     *
     * @param  array<string, mixed>  $refund  Stripe refund object
     */
    public function applyRefund(array $refund): void
    {
        $intent = is_string($refund['payment_intent'] ?? null) ? $refund['payment_intent'] : null;
        $order = $intent === null ? null : MarketplaceOrder::query()->where('stripe_payment_intent', $intent)->first();

        if ($order === null || ($refund['status'] ?? null) !== 'succeeded' || ! is_int($refund['amount'] ?? null)) {
            return;
        }

        $amount = StripeAmount::toMoney($refund['amount'], (string) ($refund['currency'] ?? $order->currency));

        if ($order->status === MarketplaceOrder::PAID && $amount->currency() === $order->currency && ! $amount->lessThan($order->money())) {
            $this->markRefunded($order, 'Refunded in Stripe.', null);
        }
    }

    public function configured(): bool
    {
        return is_string(config('ecomodular.marketplace.server.stripe_secret')) && config('ecomodular.marketplace.server.stripe_secret') !== '';
    }

    private function secret(): string
    {
        if (! $this->configured()) {
            throw new GatewayException('Marketplace payments are not configured (MARKETPLACE_STRIPE_SECRET).');
        }

        return (string) config('ecomodular.marketplace.server.stripe_secret');
    }
}
