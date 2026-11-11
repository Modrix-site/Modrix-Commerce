<?php

namespace EcoModular\PhonePe;

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
 * PhonePe Payment Gateway for India (pg/v1 Standard Checkout).
 *
 * Supports UPI Intent/QR/Collect, Net Banking, Cards, and Wallets with
 * X-VERIFY checksum security and server-side status inquiry.
 */
final class PhonePeGateway implements PaymentGateway
{
    public const CODE = 'phonepe';

    public function __construct(
        private readonly PhonePeSettings $settings,
        private readonly PhonePeApi $api,
        private readonly PhonePeSignature $signature,
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
        return 'Pay securely using PhonePe UPI (Google Pay, PhonePe, Paytm, QR code), Net Banking across major banks, or Cards.';
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
            throw new GatewayException('PhonePe is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("PhonePe is not configured for currency {$payment->currency}.");
        }

        $merchantId = $this->settings->merchantId();
        if ($merchantId === null) {
            throw new GatewayException('PhonePe Merchant ID is missing.');
        }

        $txnId = 'PHONEPE-'.$payment->id.'-'.substr(hash_hmac('sha256', (string) $payment->id, (string) config('app.key')), 0, 16);
        $amountInPaise = (int) round((float) $payment->money()->toDisplayDecimal() * 100);
        $redirectUrl = route('modrixcommerce.phonepe.response').'?txn='.$txnId;
        $callbackUrl = route('webhooks.payments', self::CODE);

        $payload = [
            'merchantId' => $merchantId,
            'merchantTransactionId' => $txnId,
            'merchantUserId' => 'CUST_'.($order->user_id ?? 'GUEST_'.$order->id),
            'amount' => $amountInPaise,
            'redirectUrl' => $redirectUrl,
            'redirectMode' => 'REDIRECT',
            'callbackUrl' => $callbackUrl,
            'paymentInstrument' => [
                'type' => 'PAY_PAGE',
            ],
        ];

        try {
            $response = $this->api->pay($payload);
        } catch (Throwable $e) {
            throw new GatewayException('Failed to initiate PhonePe payment: '.$e->getMessage());
        }

        $checkoutUrl = $response['data']['instrumentResponse']['redirectInfo']['url'] ?? null;

        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            $msg = $response['message'] ?? 'Could not retrieve payment URL from PhonePe.';
            throw new GatewayException("PhonePe: {$msg}");
        }

        return new PaymentSession(
            $txnId,
            $checkoutUrl,
            now()->addMinutes(30),
        );
    }

    public function expireSession(Payment $payment): void
    {
        // PhonePe sessions expire on their server automatically after checkout window
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $sessionRef = (string) $payment->provider_reference;

        try {
            $response = $this->api->checkStatus($sessionRef);
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment status from PhonePe: '.$e->getMessage());
        }

        return $this->buildPaymentUpdate($response, $sessionRef);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $saltKey = $this->settings->saltKey();
        $saltIndex = $this->settings->saltIndex();

        if ($saltKey === null || $saltKey === '') {
            throw new InvalidWebhook('PhonePe salt key is not configured.');
        }

        $xVerify = (string) ($headers['x-verify'] ?? $headers['x_verify'] ?? '');

        if ($xVerify === '') {
            throw new InvalidWebhook('PhonePe X-VERIFY signature header is missing.');
        }

        if (! $this->signature->verifyWebhook($payload, $xVerify, $saltKey, $saltIndex)) {
            throw new InvalidWebhook('PhonePe X-VERIFY signature verification failed.');
        }

        $data = json_decode($payload, true);

        if (! is_array($data) || $data === []) {
            throw new InvalidWebhook('PhonePe webhook payload could not be parsed as JSON.');
        }

        // PhonePe can send base64 encoded response inside 'response' key
        if (isset($data['response']) && is_string($data['response'])) {
            $decoded = json_decode(base64_decode($data['response']), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        $txnId = (string) ($data['merchantTransactionId'] ?? $event['merchantTransactionId'] ?? uniqid('phonepe_', true));
        $code = strtoupper((string) ($event['code'] ?? ''));

        // Check if this is a refund event
        if (isset($data['originalTransactionId']) || str_contains($code, 'REFUND')) {
            $refundId = (string) ($data['transactionId'] ?? $txnId);
            $origTxnId = (string) ($data['originalTransactionId'] ?? '');
            $amountInPaise = (int) ($data['amount'] ?? 0);
            $amount = Money::of(number_format($amountInPaise / 100, 2, '.', ''), 'INR');

            $status = match ($code) {
                'PAYMENT_SUCCESS', 'REFUND_SUCCESS' => RefundUpdate::SUCCEEDED,
                'PAYMENT_PENDING', 'REFUND_PENDING' => RefundUpdate::PENDING,
                default => RefundUpdate::FAILED,
            };

            return new GatewayEvent(
                $refundId,
                'refund.updated',
                null,
                new RefundUpdate(
                    $refundId,
                    $origTxnId !== '' ? $origTxnId : null,
                    $amount,
                    $status,
                    $event['message'] ?? null,
                ),
            );
        }

        $update = $this->buildPaymentUpdate($event, $txnId);

        return new GatewayEvent(
            $txnId,
            'payment.updated',
            $update,
            null,
        );
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $merchantId = $this->settings->merchantId() ?? throw new GatewayException('PhonePe Merchant ID is missing.');
        $originalTxnId = (string) $payment->provider_reference;
        $refundTxnId = 'REF-'.substr($idempotencyKey, 0, 20);
        $amountInPaise = (int) round((float) $amount->toDisplayDecimal() * 100);

        $payload = [
            'merchantId' => $merchantId,
            'transactionId' => $refundTxnId,
            'originalTransactionId' => $originalTxnId,
            'amount' => $amountInPaise,
            'callbackUrl' => route('webhooks.payments', self::CODE),
        ];

        try {
            $response = $this->api->refund($payload);
        } catch (Throwable $e) {
            throw new GatewayException('PhonePe refund request failed: '.$e->getMessage());
        }

        $code = strtoupper((string) ($response['code'] ?? ''));
        $status = match ($code) {
            'PAYMENT_SUCCESS', 'REFUND_SUCCESS' => RefundUpdate::SUCCEEDED,
            'PAYMENT_PENDING', 'REFUND_PENDING' => RefundUpdate::PENDING,
            default => RefundUpdate::FAILED,
        };

        return new RefundUpdate(
            (string) ($response['data']['transactionId'] ?? $refundTxnId),
            $originalTxnId,
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
     * @param  array<string, mixed>  $response
     */
    private function buildPaymentUpdate(array $response, string $sessionReference): PaymentUpdate
    {
        $code = strtoupper((string) ($response['code'] ?? ''));
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $providerPaymentId = isset($data['transactionId']) ? (string) $data['transactionId'] : null;

        $amount = null;
        if (isset($data['amount'])) {
            $amount = Money::of(number_format((float) $data['amount'] / 100, 2, '.', ''), 'INR');
        }

        $kind = match ($code) {
            'PAYMENT_SUCCESS' => PaymentUpdateKind::Succeeded,
            'PAYMENT_PENDING' => PaymentUpdateKind::Pending,
            default => PaymentUpdateKind::Failed,
        };

        return new PaymentUpdate(
            $kind,
            $sessionReference,
            $providerPaymentId,
            $amount,
            $kind === PaymentUpdateKind::Failed ? ($response['message'] ?? 'Payment was declined or failed.') : null,
        );
    }
}
