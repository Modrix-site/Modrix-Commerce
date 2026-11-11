<?php

namespace EcoModular\NGenius;

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
 * Network International (N-Genius) Online Payment Gateway.
 *
 * Primary payment acquirer and gateway across the UAE and Middle East.
 * Implements hosted checkout, 3DS verification, server-side retrieval, and refunds.
 */
final class NGeniusGateway implements PaymentGateway
{
    public const CODE = 'ngenius';

    public function __construct(
        private readonly NGeniusSettings $settings,
        private readonly NGeniusApi $api,
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
        return 'Pay securely with Network International (N-Genius) using Credit / Debit Card, Apple Pay, or regional payment methods.';
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
            throw new GatewayException('Network International (N-Genius) is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("N-Genius is not configured for currency {$payment->currency}.");
        }

        $merchantOrderReference = 'ORD-'.$order->number.'-'.$payment->id;
        $minorAmount = NGeniusAmount::toMinorUnits($payment->money());

        $callbackUrl = route('modrixcommerce.ngenius.response').'?payment_id='.$payment->id;

        $payload = [
            'action' => $this->settings->action(),
            'amount' => [
                'currencyCode' => $payment->currency,
                'value' => $minorAmount,
            ],
            'merchantOrderReference' => $merchantOrderReference,
            'emailAddress' => $order->email,
            'redirectUrl' => $callbackUrl,
            'cancelUrl' => $cancelUrl,
            'billingAddress' => [
                'firstName' => $order->shippingAddress?->first_name ?? $order->customer?->first_name ?? 'Customer',
                'lastName' => $order->shippingAddress?->last_name ?? $order->customer?->last_name ?? 'Order',
                'address1' => $order->shippingAddress?->address_line_1 ?? 'UAE',
                'city' => $order->shippingAddress?->city ?? 'Dubai',
                'countryCode' => $order->shippingAddress?->country_code ?? 'AE',
            ],
        ];

        try {
            $response = $this->api->createOrder($payload);
        } catch (Throwable $e) {
            throw new GatewayException('Failed to create N-Genius payment order: '.$e->getMessage(), 0, $e);
        }

        $orderReference = (string) ($response['reference'] ?? '');
        $paymentUrl = $response['_links']['payment']['href'] ?? null;

        if ($orderReference === '' || ! is_string($paymentUrl) || $paymentUrl === '') {
            $msg = $response['message'] ?? 'Could not retrieve payment URL from N-Genius.';
            throw new GatewayException("N-Genius: {$msg}");
        }

        return new PaymentSession(
            $orderReference,
            $paymentUrl,
            now()->addMinutes(30),
        );
    }

    public function expireSession(Payment $payment): void
    {
        // N-Genius orders expire automatically on the gateway server
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $orderReference = (string) $payment->provider_reference;

        if ($orderReference === '') {
            throw new GatewayException('Payment has no N-Genius order reference.');
        }

        try {
            $response = $this->api->getOrder($orderReference);
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment status from N-Genius: '.$e->getMessage(), 0, $e);
        }

        return $this->buildPaymentUpdate($response, $orderReference);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $secret = $this->settings->webhookSecret();

        if ($secret !== null && $secret !== '') {
            $signature = (string) ($headers['x-signature'] ?? $headers['signature'] ?? '');
            if ($signature === '') {
                throw new InvalidWebhook('N-Genius webhook signature header is missing.');
            }

            $expected = hash_hmac('sha256', $payload, $secret);
            if (! hash_equals($expected, $signature)) {
                throw new InvalidWebhook('N-Genius webhook signature verification failed.');
            }
        }

        $data = json_decode($payload, true);
        if (! is_array($data) || empty($data)) {
            throw new InvalidWebhook('N-Genius webhook payload is not valid JSON.');
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $eventName = strtoupper((string) ($event['eventName'] ?? $event['event'] ?? ''));
        $orderData = is_array($event['order'] ?? null) ? $event['order'] : $event;
        $orderRef = (string) ($orderData['reference'] ?? $orderData['orderReference'] ?? uniqid('ngenius_', true));

        // If this is a refund event
        if (str_contains($eventName, 'REFUND') || isset($event['refund'])) {
            $refundData = is_array($event['refund'] ?? null) ? $event['refund'] : $event;
            $refundRef = (string) ($refundData['reference'] ?? uniqid('ref_', true));
            $paymentRef = (string) ($refundData['paymentReference'] ?? $orderRef);

            $amountVal = (int) ($refundData['amount']['value'] ?? 0);
            $currency = (string) ($refundData['amount']['currencyCode'] ?? 'AED');
            $money = NGeniusAmount::fromMinorUnits($amountVal, $currency);

            $status = match ($eventName) {
                'REFUND_SUCCESS', 'REFUND_CAPTURED', 'REFUND_SETTLED' => RefundUpdate::SUCCEEDED,
                'REFUND_PENDING', 'REFUND_STARTED' => RefundUpdate::PENDING,
                default => RefundUpdate::FAILED,
            };

            return new GatewayEvent(
                $refundRef,
                'refund.updated',
                null,
                new RefundUpdate(
                    $refundRef,
                    $paymentRef,
                    $money,
                    $status,
                    $event['message'] ?? null,
                ),
            );
        }

        $update = $this->buildPaymentUpdate($event, $orderRef);

        return new GatewayEvent(
            $orderRef,
            'payment.updated',
            $update,
            null,
        );
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $orderRef = (string) $payment->provider_reference;
        if ($orderRef === '') {
            throw new GatewayException('Cannot refund: N-Genius order reference is missing.');
        }

        $paymentRef = (string) ($payment->provider_payment_id ?? '');

        // If provider_payment_id was not yet set on payment, retrieve order to locate payment reference
        if ($paymentRef === '') {
            try {
                $orderData = $this->api->getOrder($orderRef);
                $payments = $orderData['_embedded']['payment'] ?? [];
                if (! empty($payments) && isset($payments[0]['reference'])) {
                    $paymentRef = (string) $payments[0]['reference'];
                }
            } catch (Throwable $e) {
                throw new GatewayException('Failed to discover N-Genius payment reference for refund: '.$e->getMessage(), 0, $e);
            }
        }

        if ($paymentRef === '') {
            throw new GatewayException('No successful payment found on N-Genius order to refund.');
        }

        $payload = [
            'amount' => [
                'currencyCode' => $amount->currency(),
                'value' => NGeniusAmount::toMinorUnits($amount),
            ],
        ];

        try {
            $response = $this->api->refund($orderRef, $paymentRef, $payload);
        } catch (Throwable $e) {
            throw new GatewayException('N-Genius refund request failed: '.$e->getMessage(), 0, $e);
        }

        $refundRef = (string) ($response['reference'] ?? ('ref_'.substr($idempotencyKey, 0, 18)));
        $state = strtoupper((string) ($response['state'] ?? 'SUCCESS'));

        $status = match ($state) {
            'SUCCESS', 'CAPTURED', 'REFUNDED' => RefundUpdate::SUCCEEDED,
            'PENDING', 'STARTED' => RefundUpdate::PENDING,
            default => RefundUpdate::FAILED,
        };

        return new RefundUpdate(
            $refundRef,
            $paymentRef,
            $amount,
            $status,
            $response['message'] ?? null,
        );
    }

    public function testConnection(): void
    {
        $this->api->testConnection();
    }

    /**
     * @param  array<string, mixed>  $orderResponse
     */
    public function buildPaymentUpdate(array $orderResponse, string $orderReference): PaymentUpdate
    {
        $payments = $orderResponse['_embedded']['payment'] ?? [];
        $latestPayment = ! empty($payments) && is_array($payments[0]) ? $payments[0] : null;

        $providerPaymentId = $latestPayment['reference'] ?? null;
        if (is_string($providerPaymentId)) {
            $providerPaymentId = trim($providerPaymentId);
        }

        $state = strtoupper((string) ($latestPayment['state'] ?? $orderResponse['state'] ?? ''));

        $kind = match ($state) {
            'CAPTURED', 'PURCHASED', 'AUTHORISED' => PaymentUpdateKind::Succeeded,
            'STARTED', 'AWAIT_3DS', 'PENDING' => PaymentUpdateKind::Pending,
            'PROCESSING' => PaymentUpdateKind::Processing,
            'EXPIRED' => PaymentUpdateKind::Expired,
            default => PaymentUpdateKind::Failed,
        };

        $amount = null;
        $amountData = $latestPayment['amount'] ?? $orderResponse['amount'] ?? null;
        if (is_array($amountData) && isset($amountData['value'], $amountData['currencyCode'])) {
            $amount = NGeniusAmount::fromMinorUnits((int) $amountData['value'], (string) $amountData['currencyCode']);
        }

        $failureReason = null;
        if ($kind === PaymentUpdateKind::Failed) {
            $failureReason = $latestPayment['authResponse']['authorizationMessage']
                ?? $orderResponse['message']
                ?? 'Payment was declined or failed on N-Genius.';
        }

        return new PaymentUpdate(
            $kind,
            $orderReference,
            $providerPaymentId,
            $amount,
            $failureReason,
        );
    }
}
