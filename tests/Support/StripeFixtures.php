<?php

namespace Tests\Support;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Gateways\Stripe\StripeSettings;
use App\Domains\Payments\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Stripe test doubles: fake keys, faked REST responses and webhook payloads
 * signed exactly as Stripe signs them. No network access.
 */
trait StripeFixtures
{
    protected string $stripeSecretKey = 'sk_test_51FakeKeyForAutomatedTests0001';

    protected string $stripeWebhookSecret = 'whsec_FakeSigningSecretForTests0001';

    protected function configureStripe(bool $enabled = true): void
    {
        app(StripeSettings::class)->update($enabled, 'Card', $this->stripeSecretKey, $this->stripeWebhookSecret);
    }

    /**
     * Fakes session creation (and anything else) at the Stripe API.
     *
     * @param  array<string, mixed>  $extra  url pattern => response
     */
    protected function fakeStripeApi(array $extra = []): void
    {
        Http::fake([
            ...$extra,
            'api.stripe.com/v1/checkout/sessions/*/expire' => Http::response(['id' => 'cs_x', 'status' => 'expired']),
            'api.stripe.com/v1/checkout/sessions' => fn () => Http::response([
                'id' => 'cs_test_'.Str::random(24),
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_fake',
                'expires_at' => now()->addMinutes(30)->getTimestamp(),
                'status' => 'open',
            ]),
        ]);
    }

    /**
     * Places an order paid by Stripe and returns it with its pending payment.
     *
     * @return array{0: Order, 1: Payment}
     */
    protected function placeStripeOrder(string $price = '20.00', int $quantity = 1): array
    {
        $variant = $this->sellable($price, 10);
        $this->addToCart($variant, $quantity);
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'stripe']))->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();
        $payment = $order->payments()->latest('id')->firstOrFail();

        return [$order, $payment];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    protected function stripeEvent(string $type, array $object, ?string $id = null): array
    {
        return [
            'id' => $id ?? 'evt_'.Str::random(24),
            'object' => 'event',
            'type' => $type,
            'created' => time(),
            'data' => ['object' => $object],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function completedSession(Payment $payment, ?int $amountTotal = null, string $paymentStatus = 'paid', ?string $currency = null): array
    {
        return [
            'id' => $payment->provider_reference,
            'object' => 'checkout.session',
            'status' => 'complete',
            'payment_status' => $paymentStatus,
            'amount_total' => $amountTotal ?? (int) round(((float) $payment->amount) * 100),
            'currency' => strtolower($currency ?? $payment->currency),
            'payment_intent' => 'pi_test_'.$payment->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function postStripeWebhook(array $event, ?string $secret = null, ?int $timestamp = null, ?string $signature = null): TestResponse
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp ??= time();
        $signature ??= 't='.$timestamp.',v1='.hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? $this->stripeWebhookSecret);

        return $this->call('POST', route('webhooks.payments', 'stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);
    }
}
