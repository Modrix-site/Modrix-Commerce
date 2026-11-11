<?php

namespace EcoModular\Cashfree;

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
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cashfree Payments gateway for India (REST API v2023-08-01).
 *
 * Implements UPI, Netbanking, Cards, Wallets, server verification,
 * HMAC-SHA256 signed webhooks, and instant API refunds.
 */
final class CashfreeGateway implements PaymentGateway
{
    public const CODE = 'cashfree';

    public function __construct(
        private readonly CashfreeSettings $settings,
        private readonly CashfreeApi $api,
        private readonly CashfreeSignature $signature,
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
        return 'Pay securely using UPI (Google Pay, PhonePe, Paytm, BHIM), Net Banking, Cards, or Wallets via Cashfree Payments.';
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
            throw new GatewayException('Cashfree Payments is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("Cashfree Payments is not configured for currency {$payment->currency}.");
        }

        $orderId = 'CF-'.$payment->id.'-'.substr(hash_hmac('sha256', (string) $payment->id, (string) config('app.key')), 0, 16);
        $responseUrl = route('modrixcommerce.cashfree.response').'?order_id='.$orderId;
        $webhookUrl = route('webhooks.payments', self::CODE);

        $customerPhone = preg_replace('/[^0-9]/', '', (string) ($order->phone ?? ''));
        if (strlen($customerPhone) < 10) {
            $customerPhone = '9999999999';
        }

        $body = [
            'order_id' => $orderId,
            'order_amount' => (float) $payment->money()->toDisplayDecimal(),
            'order_currency' => $payment->currency,
            'customer_details' => [
                'customer_id' => 'cust_'.($order->user_id ?? 'guest_'.$order->id),
                'customer_email' => mb_substr($order->email ?? '', 0, 254),
                'customer_phone' => mb_substr($customerPhone, -10),
                'customer_name' => mb_substr($order->customer_name ?? 'Customer', 0, 100),
            ],
            'order_meta' => [
                'return_url' => $responseUrl,
                'notify_url' => $webhookUrl,
            ],
        ];

        try {
            $response = $this->api->createOrder($body);
        } catch (Throwable $e) {
            throw new GatewayException('Failed to initiate Cashfree payment: '.$e->getMessage());
        }

        $paymentSessionId = (string) ($response['payment_session_id'] ?? '');

        if ($paymentSessionId === '') {
            throw new GatewayException('Cashfree did not return a valid payment session ID.');
        }

        Cache::put("cashfree:handoff:{$payment->id}", [
            'paymentSessionId' => $paymentSessionId,
            'mode' => $this->api->sdkMode(),
            'orderId' => $orderId,
            'orderNumber' => $order->number ?? (string) $order->id,
            'amount' => $payment->money()->toDisplayDecimal(),
            'currency' => $payment->currency,
        ], now()->addMinutes(30));

        $handoffUrl = route('modrixcommerce.cashfree.handoff', $payment);

        return new PaymentSession(
            $orderId,
            $handoffUrl,
            now()->addMinutes(30),
        );
    }

    public function expireSession(Payment $payment): void
    {
        Cache::forget("cashfree:handoff:{$payment->id}");
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $sessionRef = (string) $payment->provider_reference;

        try {
            $order = $this->api->getOrder($sessionRef);
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment from Cashfree: '.$e->getMessage());
        }

        $status = strtoupper((string) ($order['order_status'] ?? ''));
        $cfOrderId = isset($order['cf_order_id']) ? (string) $order['cf_order_id'] : null;

        $amount = null;
        if (isset($order['order_amount'], $order['order_currency'])) {
            $amount = Money::of((string) $order['order_amount'], (string) $order['order_currency']);
        }

        $kind = match ($status) {
            'PAID' => PaymentUpdateKind::Succeeded,
            'ACTIVE' => PaymentUpdateKind::Pending,
            'EXPIRED' => PaymentUpdateKind::Expired,
            default => PaymentUpdateKind::Failed,
        };

        return new PaymentUpdate(
            $kind,
            $sessionRef,
            $cfOrderId,
            $amount,
            $kind === PaymentUpdateKind::Failed ? "Cashfree order status: {$status}" : null,
        );
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $secretKey = $this->settings->secretKey();

        if ($secretKey === null || $secretKey === '') {
            throw new InvalidWebhook('Cashfree secret key is not configured.');
        }

        $timestamp = (string) ($headers['x-webhook-timestamp'] ?? '');
        $signature = (string) ($headers['x-webhook-signature'] ?? '');

        if ($timestamp === '' || $signature === '') {
            throw new InvalidWebhook('Cashfree webhook headers (timestamp or signature) are missing.');
        }

        if (! $this->signature->verify($payload, $signature, $timestamp, $secretKey)) {
            throw new InvalidWebhook('Cashfree webhook signature verification failed.');
        }

        $data = json_decode($payload, true);

        if (! is_array($data) || $data === []) {
            throw new InvalidWebhook('Cashfree webhook body could not be parsed as JSON.');
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $type = (string) ($event['type'] ?? '');
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];

        if (str_contains($type, 'REFUND')) {
            $refundData = is_array($data['refund'] ?? null) ? $data['refund'] : $data;
            $cfRefundId = (string) ($refundData['cf_refund_id'] ?? $refundData['refund_id'] ?? uniqid('cf_ref_', true));
            $orderId = (string) ($refundData['order_id'] ?? '');
            $amount = isset($refundData['refund_amount'], $refundData['refund_currency'])
                ? Money::of((string) $refundData['refund_amount'], (string) $refundData['refund_currency'])
                : Money::zero('INR');

            $statusStr = strtoupper((string) ($refundData['refund_status'] ?? ''));
            $status = match ($statusStr) {
                'SUCCESS' => RefundUpdate::SUCCEEDED,
                'PENDING' => RefundUpdate::PENDING,
                default => RefundUpdate::FAILED,
            };

            return new GatewayEvent(
                $cfRefundId,
                'refund.updated',
                null,
                new RefundUpdate(
                    $cfRefundId,
                    $orderId,
                    $amount,
                    $status,
                    $refundData['status_description'] ?? null,
                ),
            );
        }

        $orderData = is_array($data['order'] ?? null) ? $data['order'] : [];
        $paymentData = is_array($data['payment'] ?? null) ? $data['payment'] : [];

        $orderId = (string) ($orderData['order_id'] ?? $data['order_id'] ?? '');
        $cfPaymentId = (string) ($paymentData['cf_payment_id'] ?? $data['cf_payment_id'] ?? '');
        $paymentStatus = strtoupper((string) ($paymentData['payment_status'] ?? $data['payment_status'] ?? ''));

        $amount = null;
        if (isset($paymentData['payment_amount'], $paymentData['payment_currency'])) {
            $amount = Money::of((string) $paymentData['payment_amount'], (string) $paymentData['payment_currency']);
        } elseif (isset($orderData['order_amount'], $orderData['order_currency'])) {
            $amount = Money::of((string) $orderData['order_amount'], (string) $orderData['order_currency']);
        }

        $kind = match ($paymentStatus) {
            'SUCCESS' => PaymentUpdateKind::Succeeded,
            'PENDING', 'USER_DROPPED' => PaymentUpdateKind::Pending,
            default => PaymentUpdateKind::Failed,
        };

        $eventId = $cfPaymentId !== '' ? $cfPaymentId : ($orderId !== '' ? $orderId : uniqid('cf_', true));

        return new GatewayEvent(
            $eventId,
            'payment.updated',
            new PaymentUpdate(
                $kind,
                $orderId,
                $cfPaymentId !== '' ? $cfPaymentId : null,
                $amount,
                $kind === PaymentUpdateKind::Failed ? ($paymentData['payment_message'] ?? 'Payment failed.') : null,
            ),
            null,
        );
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $orderId = (string) $payment->provider_reference;
        $refundId = 'CF-REF-'.substr($idempotencyKey, 0, 20);

        $body = [
            'refund_id' => $refundId,
            'refund_amount' => (float) $amount->toDisplayDecimal(),
            'refund_note' => 'Order refund',
        ];

        try {
            $response = $this->api->refund($orderId, $body);
        } catch (Throwable $e) {
            throw new GatewayException('Cashfree refund request failed: '.$e->getMessage());
        }

        $statusStr = strtoupper((string) ($response['refund_status'] ?? ''));
        $status = match ($statusStr) {
            'SUCCESS' => RefundUpdate::SUCCEEDED,
            'PENDING' => RefundUpdate::PENDING,
            default => RefundUpdate::FAILED,
        };

        return new RefundUpdate(
            (string) ($response['cf_refund_id'] ?? $refundId),
            $orderId,
            $amount,
            $status,
            $response['status_description'] ?? null,
        );
    }

    public function testConnection(): void
    {
        $this->api->testConnection();
    }
}
