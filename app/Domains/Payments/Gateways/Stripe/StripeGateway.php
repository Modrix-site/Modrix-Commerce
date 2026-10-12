<?php

namespace App\Domains\Payments\Gateways\Stripe;

use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Contracts\PaymentGateway;
use App\Domains\Payments\Gateway\GatewayEvent;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Gateway\InvalidWebhook;
use App\Domains\Payments\Gateway\PaymentSession;
use App\Domains\Payments\Gateway\PaymentUpdate;
use App\Domains\Payments\Gateway\PaymentUpdateKind;
use App\Domains\Payments\Gateway\RefundUpdate;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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
    /** Pinned so payload shapes do not change underneath us. */
    public const API_VERSION = '2024-06-20';

    /** Maximum accepted age of a webhook signature timestamp, in seconds. */
    public const SIGNATURE_TOLERANCE = 300;

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
        return 'You will be redirected to Stripe to pay securely. Your card details are never shared with us.';
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

        $session = $this->send('post', 'v1/checkout/sessions', [
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
        ], idempotencyKey: "ecomodular-payment-{$payment->id}");

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
        $secret = $this->settings->webhookSecret() ?? throw new InvalidWebhook('Stripe webhooks are not configured.');
        $header = $headers['stripe-signature'] ?? '';
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new InvalidWebhook('Missing or malformed Stripe-Signature header.');
        }

        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE) {
            throw new InvalidWebhook('Stripe signature timestamp is outside the tolerance window.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
        $valid = array_filter($signatures, fn (string $signature) => hash_equals($expected, $signature));

        if ($valid === []) {
            throw new InvalidWebhook('Stripe signature does not match.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null) || ! is_array($event['data']['object'] ?? null)) {
            throw new InvalidWebhook('Stripe event payload is malformed.');
        }

        return $event;
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

        try {
            $response = $this->client($key, $idempotencyKey)->{$method}($path, $params);
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach Stripe. Please try again.', retryable: true);
        }

        if ($response->failed()) {
            throw $this->error($response);
        }

        $body = $response->json();

        return is_array($body) ? $body : throw new GatewayException('Stripe returned an unreadable response.');
    }

    private function client(string $key, ?string $idempotencyKey): PendingRequest
    {
        $request = Http::baseUrl((string) config('services.stripe.base_url', 'https://api.stripe.com'))
            ->withToken($key)
            ->withHeaders(['Stripe-Version' => self::API_VERSION])
            ->acceptJson()
            ->asForm()
            ->connectTimeout(5)
            ->timeout(20)
            // Safe to retry: creating requests carry an idempotency key.
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);

        return $idempotencyKey === null ? $request : $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
    }

    private function error(Response $response): GatewayException
    {
        $message = (string) ($response->json('error.message') ?? 'Stripe rejected the request.');
        // Never echo anything that looks like a key back to the UI or logs.
        $message = (string) preg_replace('/\b(sk|rk|pk|whsec)_[A-Za-z0-9_]+/', '[redacted]', $message);
        $retryable = $response->status() === 429 || $response->serverError();

        return new GatewayException("Stripe: {$message}", $retryable);
    }
}
