<?php

namespace EcoModular\Paytm;

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
 * Paytm Payment Gateway for India (All-in-One PG / Theia API).
 *
 * Supports Paytm UPI, Paytm Wallet, Net Banking, and Cards with
 * PaytmChecksum AES-128 / SHA256 integrity and server-side status verification.
 */
final class PaytmGateway implements PaymentGateway
{
    public const CODE = 'paytm';

    public function __construct(
        private readonly PaytmSettings $settings,
        private readonly PaytmApi $api,
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
        return 'Pay securely using Paytm UPI (Google Pay, PhonePe, Paytm), Paytm Wallet, Net Banking across all banks, or Credit/Debit Cards.';
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
            throw new GatewayException('Paytm Payment Gateway is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("Paytm is not configured for currency {$payment->currency}.");
        }

        $mid = $this->settings->mid();
        if ($mid === null) {
            throw new GatewayException('Paytm Merchant ID is missing.');
        }

        $paytmOrderId = 'PTM_' . $payment->id . '_' . substr(hash_hmac('sha256', (string) $payment->id, (string) config('app.key')), 0, 10);
        $amount = number_format((float) $payment->money()->toDisplayDecimal(), 2, '.', '');
        $custId = 'CUST_' . ($order->user_id ?? 'GUEST_' . $order->id);

        $body = [
            'requestType' => 'Payment',
            'mid' => $mid,
            'websiteName' => $this->settings->website(),
            'orderId' => $paytmOrderId,
            'callbackUrl' => route('modrixcommerce.paytm.response'),
            'txnAmount' => [
                'value' => $amount,
                'currency' => $payment->currency,
            ],
            'userInfo' => [
                'custId' => $custId,
                'mobile' => $order->phone ?? '',
                'email' => $order->email,
            ],
        ];

        try {
            $response = $this->api->initiateTransaction($paytmOrderId, $body);
        } catch (Throwable $e) {
            throw new GatewayException('Failed to initiate Paytm transaction: ' . $e->getMessage(), 0, $e);
        }

        $resultStatus = $response['body']['resultInfo']['resultStatus'] ?? null;
        $txnToken = $response['body']['txnToken'] ?? null;

        if ($resultStatus !== 'S' || ! is_string($txnToken) || $txnToken === '') {
            $msg = $response['body']['resultInfo']['resultMsg'] ?? 'Could not retrieve transaction token from Paytm.';
            throw new GatewayException("Paytm: {$msg}");
        }

        $handoffUrl = route('modrixcommerce.paytm.handoff') . '?' . http_build_query([
            'token' => $txnToken,
            'order_id' => $paytmOrderId,
            'mid' => $mid,
        ]);

        return new PaymentSession(
            $paytmOrderId,
            $handoffUrl,
            now()->addMinutes(15),
        );
    }

    public function expireSession(Payment $payment): void
    {
        // Paytm txnTokens expire automatically after 15 minutes
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $orderId = (string) $payment->provider_reference;

        if ($orderId === '') {
            throw new GatewayException('Payment has no Paytm order reference.');
        }

        try {
            $response = $this->api->getTransactionStatus($orderId);
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment status from Paytm: ' . $e->getMessage(), 0, $e);
        }

        return $this->buildPaymentUpdate($response, $orderId);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $key = $this->settings->merchantKey();
        if ($key === null || $key === '') {
            throw new InvalidWebhook('Paytm Merchant Key is not configured.');
        }

        // Paytm sends callback as JSON or form-encoded POST
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            parse_str($payload, $data);
        }

        if (! is_array($data) || empty($data)) {
            throw new InvalidWebhook('Paytm webhook payload is empty or invalid.');
        }

        $checksum = (string) ($data['CHECKSUMHASH'] ?? $headers['x-checksum'] ?? '');
        if ($checksum === '') {
            throw new InvalidWebhook('Paytm checksum hash is missing from callback.');
        }

        if (! PaytmChecksum::verifySignature($data, $key, $checksum)) {
            throw new InvalidWebhook('Paytm checksum signature verification failed.');
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $data = is_array($event['body'] ?? null) ? $event['body'] : $event;
        $orderId = (string) ($data['ORDERID'] ?? $data['orderId'] ?? uniqid('ptm_', true));

        // Check if this is a refund event
        if (isset($data['refundId']) || isset($data['REFUNDID']) || str_contains((string) ($data['txnType'] ?? ''), 'REFUND')) {
            $refundId = (string) ($data['refundId'] ?? $data['REFUNDID'] ?? uniqid('ptm_ref_', true));
            $txnId = (string) ($data['txnId'] ?? $data['TXNID'] ?? '');
            $amountVal = (string) ($data['refundAmount'] ?? $data['REFUNDAMOUNT'] ?? '0.00');
            $amount = Money::of($amountVal, 'INR');

            $status = match (strtoupper((string) ($data['resultInfo']['resultStatus'] ?? $data['STATUS'] ?? ''))) {
                'TXN_SUCCESS', 'SUCCESS' => RefundUpdate::SUCCEEDED,
                'PENDING' => RefundUpdate::PENDING,
                default => RefundUpdate::FAILED,
            };

            return new GatewayEvent(
                $refundId,
                'refund.updated',
                null,
                new RefundUpdate(
                    $refundId,
                    $txnId !== '' ? $txnId : null,
                    $amount,
                    $status,
                    $data['resultInfo']['resultMsg'] ?? $data['RESPMSG'] ?? null,
                ),
            );
        }

        $update = $this->buildPaymentUpdate($event, $orderId);

        return new GatewayEvent(
            $orderId,
            'payment.updated',
            $update,
            null,
        );
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $orderId = (string) $payment->provider_reference;
        if ($orderId === '') {
            throw new GatewayException('Paytm order reference is missing from payment.');
        }

        $txnId = (string) ($payment->provider_payment_id ?? '');

        if ($txnId === '') {
            try {
                $statusData = $this->api->getTransactionStatus($orderId);
                $txnId = (string) ($statusData['body']['txnId'] ?? '');
            } catch (Throwable $e) {
                throw new GatewayException('Could not discover Paytm txnId for refund: ' . $e->getMessage(), 0, $e);
            }
        }

        if ($txnId === '') {
            throw new GatewayException('No Paytm transaction ID found for refund.');
        }

        $refId = 'REF_' . substr($idempotencyKey, 0, 20);
        $refundAmount = number_format((float) $amount->toDisplayDecimal(), 2, '.', '');

        try {
            $response = $this->api->refund($orderId, $txnId, $refId, $refundAmount);
        } catch (Throwable $e) {
            throw new GatewayException('Paytm refund request failed: ' . $e->getMessage(), 0, $e);
        }

        $body = $response['body'] ?? [];
        $resultStatus = strtoupper((string) ($body['resultInfo']['resultStatus'] ?? ''));
        $refundId = (string) ($body['refundId'] ?? $refId);

        $status = match ($resultStatus) {
            'TXN_SUCCESS', 'SUCCESS' => RefundUpdate::SUCCEEDED,
            'PENDING' => RefundUpdate::PENDING,
            default => RefundUpdate::FAILED,
        };

        return new RefundUpdate(
            $refundId,
            $txnId,
            $amount,
            $status,
            $body['resultInfo']['resultMsg'] ?? null,
        );
    }

    public function testConnection(): void
    {
        $res = $this->api->testConnection();
        if (! $res['success']) {
            throw new GatewayException($res['error'] ?? 'Connection to Paytm failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function buildPaymentUpdate(array $response, string $orderId): PaymentUpdate
    {
        $data = is_array($response['body'] ?? null) ? $response['body'] : $response;

        $status = strtoupper((string) ($data['resultInfo']['resultStatus'] ?? $data['STATUS'] ?? ''));
        $providerPaymentId = $data['txnId'] ?? $data['TXNID'] ?? null;
        if (is_string($providerPaymentId)) {
            $providerPaymentId = trim($providerPaymentId);
        }

        $kind = match ($status) {
            'TXN_SUCCESS' => PaymentUpdateKind::Succeeded,
            'PENDING' => PaymentUpdateKind::Pending,
            default => PaymentUpdateKind::Failed,
        };

        $amount = null;
        $rawAmount = $data['txnAmount'] ?? $data['TXNAMOUNT'] ?? null;
        if (is_numeric($rawAmount)) {
            $amount = Money::of(number_format((float) $rawAmount, 2, '.', ''), 'INR');
        }

        $failureReason = null;
        if ($kind === PaymentUpdateKind::Failed) {
            $failureReason = $data['resultInfo']['resultMsg'] ?? $data['RESPMSG'] ?? 'Payment was declined or failed on Paytm.';
        }

        return new PaymentUpdate(
            $kind,
            $orderId,
            $providerPaymentId,
            $amount,
            $failureReason,
        );
    }
}
