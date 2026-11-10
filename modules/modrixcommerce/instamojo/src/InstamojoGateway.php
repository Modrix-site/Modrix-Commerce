<?php

namespace EcoModular\Instamojo;

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
 * Instamojo Payment Gateway for India.
 *
 * Supports UPI, Credit/Debit Cards, Net Banking, and Wallets with
 * HMAC-SHA1 MAC webhook integrity and Payment Request API v1.1.
 */
final class InstamojoGateway implements PaymentGateway
{
    public const CODE = 'instamojo';

    public function __construct(
        private readonly InstamojoSettings $settings,
        private readonly InstamojoApi $api,
        private readonly InstamojoSignature $signature,
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
        return 'Pay securely using Instamojo (UPI, Credit/Debit Cards, Net Banking across all banks, or Wallets).';
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
            throw new GatewayException('Instamojo Payment Gateway is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("Instamojo is not configured for currency {$payment->currency}.");
        }

        $amount = number_format((float) $payment->money()->toDisplayDecimal(), 2, '.', '');
        $callbackUrl = route('modrixcommerce.instamojo.response') . '?payment_id=' . $payment->id;
        $webhookUrl = route('webhooks.payments', self::CODE);

        $params = [
            'amount' => $amount,
            'purpose' => 'Order ' . ($order->number ?? $order->id),
            'buyer_name' => $order->shippingAddress?->first_name ?? $order->customer?->first_name ?? 'Customer',
            'email' => $order->email,
            'phone' => $order->phone ?? '',
            'redirect_url' => $callbackUrl,
            'webhook' => $webhookUrl,
            'send_email' => 'False',
            'send_sms' => 'False',
            'allow_repeated_payments' => 'False',
        ];

        try {
            $response = $this->api->createPaymentRequest($params);
        } catch (Throwable $e) {
            throw new GatewayException('Failed to create Instamojo payment request: ' . $e->getMessage(), 0, $e);
        }

        $requestData = $response['payment_request'] ?? [];
        $requestId = (string) ($requestData['id'] ?? '');
        $longUrl = (string) ($requestData['longurl'] ?? '');

        if ($requestId === '' || $longUrl === '') {
            $msg = $response['message'] ?? 'Could not retrieve payment URL from Instamojo.';
            throw new GatewayException("Instamojo: {$msg}");
        }

        return new PaymentSession(
            $requestId,
            $longUrl,
            now()->addMinutes(30),
        );
    }

    public function expireSession(Payment $payment): void
    {
        // Instamojo payment requests expire automatically after window
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $requestId = (string) $payment->provider_reference;

        if ($requestId === '') {
            throw new GatewayException('Payment has no Instamojo payment request reference.');
        }

        try {
            $response = $this->api->getPaymentRequest($requestId);
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment status from Instamojo: ' . $e->getMessage(), 0, $e);
        }

        return $this->buildPaymentUpdate($response, $requestId);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $privateSalt = $this->settings->privateSalt();

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            parse_str($payload, $data);
        }

        if (! is_array($data) || empty($data)) {
            throw new InvalidWebhook('Instamojo webhook payload is empty or invalid.');
        }

        if ($privateSalt !== null && $privateSalt !== '') {
            $mac = (string) ($data['mac'] ?? $headers['mac'] ?? '');
            if ($mac === '') {
                throw new InvalidWebhook('Instamojo MAC signature parameter is missing.');
            }

            if (! $this->signature->verify($data, $privateSalt, $mac)) {
                throw new InvalidWebhook('Instamojo MAC signature verification failed.');
            }
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $requestId = (string) ($event['payment_request_id'] ?? $event['id'] ?? uniqid('instamojo_', true));

        // Check if this is a refund event
        if (isset($event['refund']) || str_contains((string) ($event['status'] ?? ''), 'Refund')) {
            $refundData = is_array($event['refund'] ?? null) ? $event['refund'] : $event;
            $refundId = (string) ($refundData['id'] ?? uniqid('ref_', true));
            $paymentId = (string) ($refundData['payment_id'] ?? $requestId);
            $amountVal = (string) ($refundData['refund_amount'] ?? $refundData['amount'] ?? '0.00');
            $amount = Money::of($amountVal, 'INR');

            $status = match (strtolower((string) ($refundData['status'] ?? ''))) {
                'refunded', 'completed', 'successful' => RefundUpdate::SUCCEEDED,
                'pending' => RefundUpdate::PENDING,
                default => RefundUpdate::FAILED,
            };

            return new GatewayEvent(
                $refundId,
                'refund.updated',
                null,
                new RefundUpdate(
                    $refundId,
                    $paymentId !== '' ? $paymentId : null,
                    $amount,
                    $status,
                    $refundData['body'] ?? null,
                ),
            );
        }

        $update = $this->buildPaymentUpdate($event, $requestId);

        return new GatewayEvent(
            $requestId,
            'payment.updated',
            $update,
            null,
        );
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $paymentId = (string) ($payment->provider_payment_id ?? '');

        // If provider_payment_id is not yet set, discover it from the payment request
        if ($paymentId === '') {
            $requestId = (string) $payment->provider_reference;
            if ($requestId !== '') {
                try {
                    $reqData = $this->api->getPaymentRequest($requestId);
                    $payments = $reqData['payment_request']['payments'] ?? [];
                    if (! empty($payments) && isset($payments[0]['payment_id'])) {
                        $paymentId = (string) $payments[0]['payment_id'];
                    }
                } catch (Throwable $e) {
                    throw new GatewayException('Could not discover Instamojo payment ID for refund: ' . $e->getMessage(), 0, $e);
                }
            }
        }

        if ($paymentId === '') {
            throw new GatewayException('Cannot refund: Instamojo payment ID is missing.');
        }

        $refundAmount = number_format((float) $amount->toDisplayDecimal(), 2, '.', '');

        try {
            $response = $this->api->refund($paymentId, 'RFD', 'Merchant refund via Modrix Commerce', $refundAmount);
        } catch (Throwable $e) {
            throw new GatewayException('Instamojo refund request failed: ' . $e->getMessage(), 0, $e);
        }

        $refundData = $response['refund'] ?? [];
        $refundId = (string) ($refundData['id'] ?? ('ref_' . substr($idempotencyKey, 0, 18)));
        $statusStr = strtolower((string) ($refundData['status'] ?? 'refunded'));

        $status = match ($statusStr) {
            'refunded', 'completed', 'successful' => RefundUpdate::SUCCEEDED,
            'pending' => RefundUpdate::PENDING,
            default => RefundUpdate::FAILED,
        };

        return new RefundUpdate(
            $refundId,
            $paymentId,
            $amount,
            $status,
            $refundData['body'] ?? null,
        );
    }

    public function testConnection(): void
    {
        $res = $this->api->testConnection();
        if (! $res['success']) {
            throw new GatewayException($res['error'] ?? 'Connection to Instamojo failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function buildPaymentUpdate(array $response, string $requestId): PaymentUpdate
    {
        $req = $response['payment_request'] ?? $response;
        $payments = $req['payments'] ?? [];

        $latestPayment = ! empty($payments) && is_array($payments[0]) ? $payments[0] : null;

        $providerPaymentId = $latestPayment['payment_id'] ?? $response['payment_id'] ?? null;
        if (is_string($providerPaymentId)) {
            $providerPaymentId = trim($providerPaymentId);
        }

        $status = strtolower((string) ($latestPayment['status'] ?? $req['status'] ?? $response['status'] ?? ''));

        $kind = match ($status) {
            'credit', 'completed', 'successful' => PaymentUpdateKind::Succeeded,
            'pending' => PaymentUpdateKind::Pending,
            default => PaymentUpdateKind::Failed,
        };

        $amount = null;
        $rawAmount = $latestPayment['amount'] ?? $req['amount'] ?? $response['amount'] ?? null;
        if (is_numeric($rawAmount)) {
            $amount = Money::of(number_format((float) $rawAmount, 2, '.', ''), 'INR');
        }

        $failureReason = null;
        if ($kind === PaymentUpdateKind::Failed) {
            $failureReason = $latestPayment['failure']['reason']
                ?? $response['failure_reason']
                ?? 'Payment was declined or failed on Instamojo.';
        }

        return new PaymentUpdate(
            $kind,
            $requestId,
            $providerPaymentId,
            $amount,
            $failureReason,
        );
    }
}
