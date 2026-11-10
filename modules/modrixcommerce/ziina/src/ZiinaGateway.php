<?php

namespace EcoModular\Ziina;

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
use Throwable;

/**
 * Ziina Payment Gateway for the UAE.
 *
 * Licensed by the Central Bank of the UAE.
 * Direct checkout with native Apple Pay, Google Pay, and Card processing in AED.
 */
final class ZiinaGateway implements PaymentGateway
{
    public const CODE = 'ziina';

    public function __construct(
        private readonly ZiinaSettings $settings,
        private readonly ZiinaApi $api,
    ) {}

    public function code(): string
    {
        return self::CODE;
    }

    public function provider(): string
    {
        return self::CODE;
    }

    public function label(): string
    {
        return $this->settings->label();
    }

    public function instructions(): string
    {
        return 'Pay seamlessly with Apple Pay, Google Pay, or Credit/Debit Cards using Ziina.';
    }

    public function allowsFulfillmentBeforePayment(): bool
    {
        return false;
    }

    public function isConfigured(): bool
    {
        return $this->settings->ready();
    }

    public function isAvailable(PricedCart $cart): bool
    {
        return $cart->grandTotal()->isPositive()
            && in_array($cart->grandTotal()->currency(), $this->settings->currencies(), true);
    }

    public function createSession(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): PaymentSession
    {
        if (! $this->isConfigured()) {
            throw new GatewayException('Ziina Payment Gateway is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("Ziina is not configured for currency {$payment->currency}.");
        }

        $minorAmount = ZiinaAmount::toMinorUnits($payment->money());
        $callbackUrl = route('modrixcommerce.ziina.response') . '?payment_id=' . $payment->id;

        $payload = [
            'amount' => $minorAmount,
            'currency_code' => $payment->currency,
            'message' => 'Order ' . ($order->number ?? $order->id),
            'success_url' => $callbackUrl,
            'cancel_url' => $cancelUrl,
            'test' => $this->settings->testMode(),
        ];

        try {
            $response = $this->api->createPaymentIntent($payload);
        } catch (Throwable $e) {
            throw new GatewayException('Failed to create Ziina Payment Intent: ' . $e->getMessage(), 0, $e);
        }

        $intentId = (string) ($response['id'] ?? '');
        $redirectUrl = (string) ($response['redirect_url'] ?? '');

        if ($intentId === '' || $redirectUrl === '') {
            $msg = $response['message'] ?? 'Could not retrieve redirect URL from Ziina.';
            throw new GatewayException("Ziina: {$msg}");
        }

        return new PaymentSession(
            $intentId,
            $redirectUrl,
            now()->addMinutes(30),
        );
    }

    public function expireSession(Payment $payment): void
    {
        // Ziina payment intents expire automatically on the gateway server
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $intentId = (string) $payment->provider_reference;

        if ($intentId === '') {
            throw new GatewayException('Payment has no Ziina payment intent ID.');
        }

        try {
            $response = $this->api->getPaymentIntent($intentId);
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment status from Ziina: ' . $e->getMessage(), 0, $e);
        }

        return $this->buildPaymentUpdate($response, $intentId);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $secret = $this->settings->webhookSecret();

        if ($secret !== null && $secret !== '') {
            $signature = (string) ($headers['x-hmac-signature'] ?? $headers['signature'] ?? '');
            if ($signature === '') {
                throw new InvalidWebhook('Ziina X-Hmac-Signature header is missing.');
            }

            $expected = hash_hmac('sha256', $payload, $secret);
            if (! hash_equals($expected, $signature)) {
                throw new InvalidWebhook('Ziina HMAC signature verification failed.');
            }
        }

        $data = json_decode($payload, true);
        if (! is_array($data) || empty($data)) {
            throw new InvalidWebhook('Ziina webhook payload is not valid JSON.');
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $eventName = (string) ($event['event'] ?? $event['type'] ?? '');
        $data = is_array($event['data'] ?? null) ? $event['data'] : $event;
        $id = (string) ($data['id'] ?? $event['id'] ?? uniqid('ziina_', true));

        // Check if this is a refund event
        if (str_contains($eventName, 'refund') || isset($data['payment_intent_id'])) {
            $refundId = $id;
            $paymentIntentId = (string) ($data['payment_intent_id'] ?? '');
            $amountVal = (int) ($data['amount'] ?? 0);
            $currency = (string) ($data['currency_code'] ?? 'AED');
            $money = ZiinaAmount::fromMinorUnits($amountVal, $currency);

            $status = match (strtolower((string) ($data['status'] ?? ''))) {
                'completed', 'succeeded' => RefundUpdate::SUCCEEDED,
                'pending' => RefundUpdate::PENDING,
                default => RefundUpdate::FAILED,
            };

            return new GatewayEvent(
                $refundId,
                'refund.updated',
                null,
                new RefundUpdate(
                    $refundId,
                    $paymentIntentId !== '' ? $paymentIntentId : null,
                    $money,
                    $status,
                    $data['error'] ?? null,
                ),
            );
        }

        $update = $this->buildPaymentUpdate($data, $id);

        return new GatewayEvent(
            $id,
            'payment.updated',
            $update,
            null,
        );
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $intentId = (string) $payment->provider_reference;
        if ($intentId === '') {
            throw new GatewayException('Cannot refund: Ziina payment intent ID is missing.');
        }

        $minorAmount = ZiinaAmount::toMinorUnits($amount);

        try {
            $response = $this->api->refund($intentId, $minorAmount, $amount->currency());
        } catch (Throwable $e) {
            throw new GatewayException('Ziina refund request failed: ' . $e->getMessage(), 0, $e);
        }

        $refundId = (string) ($response['id'] ?? ('ref_' . substr($idempotencyKey, 0, 18)));
        $state = strtolower((string) ($response['status'] ?? 'completed'));

        $status = match ($state) {
            'completed', 'succeeded' => RefundUpdate::SUCCEEDED,
            'pending' => RefundUpdate::PENDING,
            default => RefundUpdate::FAILED,
        };

        return new RefundUpdate(
            $refundId,
            $intentId,
            $amount,
            $status,
            $response['error'] ?? null,
        );
    }

    public function testConnection(): void
    {
        $res = $this->api->testConnection();
        if (! $res['success']) {
            throw new GatewayException($res['error'] ?? 'Connection to Ziina failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function buildPaymentUpdate(array $response, string $intentId): PaymentUpdate
    {
        $status = strtolower((string) ($response['status'] ?? ''));

        $kind = match ($status) {
            'completed' => PaymentUpdateKind::Succeeded,
            'pending', 'requires_payment_instrument', 'requires_user_action' => PaymentUpdateKind::Pending,
            default => PaymentUpdateKind::Failed,
        };

        $amount = null;
        if (isset($response['amount'], $response['currency_code'])) {
            $amount = ZiinaAmount::fromMinorUnits($response['amount'], (string) $response['currency_code']);
        }

        $failureReason = null;
        if ($kind === PaymentUpdateKind::Failed) {
            $failureReason = $response['latest_error']['message']
                ?? $response['error']
                ?? $response['message']
                ?? 'Payment was declined or failed on Ziina.';
        }

        return new PaymentUpdate(
            $kind,
            $intentId,
            $intentId,
            $amount,
            $failureReason,
        );
    }
}
