<?php

namespace App\Domains\Payments\Gateways\Stripe;

use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Contracts\PaymentGateway;
use App\Domains\Payments\Gateway\GatewayEvent;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Gateway\PaymentSession;
use App\Domains\Payments\Gateway\PaymentUpdate;
use App\Domains\Payments\Gateway\PaymentUpdateKind;
use App\Domains\Payments\Gateway\RefundUpdate;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Checkout (hosted payment page) over the REST API (ADR-025).
 *
 * Card data is entered on Stripe's page and never reaches this application.
 * Every creating request carries an idempotency key derived from our own
 * record id, so retries cannot create a second session or refund.
 */
final class StripeGateway implements PaymentGateway
{
    public const API_VERSION = StripeApi::API_VERSION;

    public const SIGNATURE_TOLERANCE = StripeApi::SIGNATURE_TOLERANCE;

    public function __construct(private readonly StripeSettings $settings) {}

    public function code(): string
    {
        return 'stripe';
    }

    public function provider(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return $this->settings->label();
    }

    public function instructions(): string
    {
        return 'You will be redirected to Stripe to pay securely. Your card details are never shared with us.'.$this->walletNote();
    }

    public function isConfigured(): bool
    {
        return $this->settings->enabled() && $this->settings->hasCredentials();
    }

    public function isAvailable(PricedCart $cart): bool
    {
        try {
            return StripeAmount::toMinor($cart->grandTotal()) >= StripeAmount::MINIMUM;
        } catch (GatewayException) {
            return false;
        }
    }

    public function allowsFulfillmentBeforePayment(): bool
    {
        return false;
    }

    public function createSession(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): PaymentSession
    {
        $amount = StripeAmount::toMinor($payment->money());
        $metadata = ['order_number' => (string) $order->number, 'payment_id' => (string) $payment->id];
        $expiresAt = now()->addMinutes($this->settings->sessionTtlMinutes());

        $body = [
            'mode' => 'payment',
            'client_reference_id' => $order->number,
            'customer_email' => $order->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => $amount,
                    'product_data' => ['name' => "Order {$order->number}"],
                ],
            ]],
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
            'success_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'expires_at' => $expiresAt->getTimestamp(),
        ];
        $configuration = $this->paymentMethodConfiguration();

        if ($configuration !== '') {
            $body['payment_method_configuration'] = $configuration;
        }

        $session = $this->send('post', 'v1/checkout/sessions', $body, idempotencyKey: "ecomodular-payment-{$payment->id}");

        if (! is_string($session['id'] ?? null) || ! is_string($session['url'] ?? null)) {
            throw new GatewayException('Stripe returned an unexpected response when creating the payment page.');
        }

        return new PaymentSession(
            $session['id'],
            $session['url'],
            is_int($session['expires_at'] ?? null) ? Carbon::createFromTimestamp($session['expires_at']) : $expiresAt,
        );
    }

    public function expireSession(Payment $payment): void
    {
        if ($payment->provider_reference === null) {
            return;
        }

        try {
            $this->send('post', 'v1/checkout/sessions/'.rawurlencode($payment->provider_reference).'/expire', []);
        } catch (GatewayException $e) {
            // Already completed or expired sessions cannot be expired; nothing to do.
            Log::info('Stripe session could not be expired.', ['payment_id' => $payment->id, 'reason' => $e->getMessage()]);
        }
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $session = $this->send('get', 'v1/checkout/sessions/'.rawurlencode((string) $payment->provider_reference), []);

        return $this->sessionUpdate($session);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        return StripeApi::verifyWebhook($payload, $headers, $this->settings->webhookSecret());
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $id = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        /** @var array<string, mixed> $object */
        $object = $event['data']['object'] ?? [];

        return match ($type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.expired' => new GatewayEvent($id, $type, payment: $this->sessionUpdate($object)),
            'checkout.session.async_payment_failed' => new GatewayEvent($id, $type, payment: new PaymentUpdate(
                PaymentUpdateKind::Failed,
                (string) ($object['id'] ?? ''),
                $this->paymentIntentId($object),
                failureReason: 'The payment could not be completed.',
            )),
            'refund.created', 'refund.updated', 'refund.failed', 'charge.refund.updated' => new GatewayEvent($id, $type, refund: $this->refundUpdate($object)),
            default => new GatewayEvent($id, $type),
        };
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        if ($payment->provider_payment_id === null) {
            throw new GatewayException('This payment has no Stripe payment reference and cannot be refunded through Stripe.');
        }

        $refund = $this->send('post', 'v1/refunds', [
            'payment_intent' => $payment->provider_payment_id,
            'amount' => StripeAmount::toMinor($amount),
            'metadata' => ['payment_id' => (string) $payment->id],
        ], idempotencyKey: $idempotencyKey);

        return $this->refundUpdate($refund);
    }

    /**
     * Confirms the credentials work (used by the admin "Test connection" button).
     *
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        $this->send('get', 'v1/balance', []);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function sessionUpdate(array $session): PaymentUpdate
    {
        $reference = (string) ($session['id'] ?? '');
        $intent = $this->paymentIntentId($session);
        $amount = is_int($session['amount_total'] ?? null) && is_string($session['currency'] ?? null)
            ? StripeAmount::toMoney($session['amount_total'], $session['currency'])
            : null;

        $kind = match (true) {
            ($session['status'] ?? null) === 'expired' => PaymentUpdateKind::Expired,
            ($session['status'] ?? null) === 'complete' && ($session['payment_status'] ?? null) === 'paid' => PaymentUpdateKind::Succeeded,
            ($session['status'] ?? null) === 'complete' && ($session['payment_status'] ?? null) === 'unpaid' => PaymentUpdateKind::Processing,
            default => PaymentUpdateKind::Pending,
        };

        return new PaymentUpdate($kind, $reference, $intent, $amount);
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function refundUpdate(array $refund): RefundUpdate
    {
        $status = match ($refund['status'] ?? null) {
            'succeeded' => RefundUpdate::SUCCEEDED,
            'failed', 'canceled' => RefundUpdate::FAILED,
            default => RefundUpdate::PENDING,
        };

        return new RefundUpdate(
            (string) ($refund['id'] ?? ''),
            $this->paymentIntentId($refund),
            StripeAmount::toMoney((int) ($refund['amount'] ?? 0), (string) ($refund['currency'] ?? 'usd')),
            $status,
            is_string($refund['failure_reason'] ?? null) ? $refund['failure_reason'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function paymentIntentId(array $object): ?string
    {
        $intent = $object['payment_intent'] ?? null;

        return match (true) {
            is_string($intent) => $intent,
            is_array($intent) && is_string($intent['id'] ?? null) => $intent['id'],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function send(string $method, string $path, array $params, ?string $idempotencyKey = null): array
    {
        $key = $this->settings->secretKey() ?? throw new GatewayException('Stripe is not configured.');

        return StripeApi::request($key, $method, $path, $params, $idempotencyKey);
    }

    /**
     * Applies the Apple Pay and Google Pay switches to a Stripe payment method
     * configuration, then reuses it for later checkouts with the same switches.
     *
     * @throws GatewayException
     */
    private function paymentMethodConfiguration(): string
    {
        $current = $this->settings->walletConfigurationId();

        if ($current !== null && $this->settings->rememberedWalletStamp() === $this->settings->walletStamp()) {
            return $current;
        }

        $params = [
            'name' => 'Modrix Commerce checkout',
            'apple_pay' => ['display_preference' => ['preference' => $this->settings->applePay() ? 'on' : 'off']],
            'google_pay' => ['display_preference' => ['preference' => $this->settings->googlePay() ? 'on' : 'off']],
        ];

        if ($current !== null) {
            try {
                $updated = $this->send('post', 'v1/payment_method_configurations/'.rawurlencode($current), $params);
                if (is_string($updated['id'] ?? null) && str_starts_with($updated['id'], 'pmc_')) {
                    $this->settings->rememberWalletConfiguration($updated['id']);

                    return $updated['id'];
                }
            } catch (GatewayException) {
                // The saved configuration is gone. Create a new one below.
            }
        }

        try {
            $created = $this->send('post', 'v1/payment_method_configurations', $params);
        } catch (GatewayException) {
            if (! $this->settings->applePay() && ! $this->settings->googlePay()) {
                return '';
            }

            throw new GatewayException('Stripe could not enable Apple Pay or Google Pay. Use a secret key that can manage payment method configurations, and enable the wallets in the Stripe Dashboard.');
        }

        $id = $created['id'] ?? null;

        if (! is_string($id) || ! str_starts_with($id, 'pmc_')) {
            throw new GatewayException('Stripe did not return a payment method configuration for Apple Pay and Google Pay.');
        }

        $this->settings->rememberWalletConfiguration($id);

        return $id;
    }

    private function walletNote(): string
    {
        $wallets = array_values(array_filter([
            $this->settings->applePay() ? 'Apple Pay' : null,
            $this->settings->googlePay() ? 'Google Pay' : null,
        ]));

        return $wallets === [] ? '' : ' '.implode(' and ', $wallets).' can be used when this device supports it.';
    }
}
