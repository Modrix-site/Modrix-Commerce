<?php

namespace EcoModular\PayBy;

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
 * PayBy (Astra Tech) Payment Gateway for the UAE.
 *
 * Supports Cards, Botim Wallet, and Apple Pay with
 * RSA-SHA256 asymmetric cryptographic signatures and server-side inquiry.
 */
final class PaybyGateway implements PaymentGateway
{
    public const CODE = 'payby';

    public function __construct(
        private readonly PaybySettings $settings,
        private readonly PaybyApi $api,
        private readonly PaybySignature $signature,
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
        return 'Pay securely using PayBy (Credit/Debit Cards, Botim Wallet, or Apple Pay).';
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
            throw new GatewayException('PayBy Payment Gateway is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("PayBy is not configured for currency {$payment->currency}.");
        }

        $merchantOrderNo = 'PAYBY_'.$payment->id.'_'.substr(hash_hmac('sha256', (string) $payment->id, (string) config('app.key')), 0, 10);
        $amount = (float) $payment->money()->toDisplayDecimal();
        $redirectUrl = route('modrixcommerce.payby.response').'?payment_id='.$payment->id;
        $notifyUrl = route('webhooks.payments', self::CODE);

        $bizContent = [
            'merchantOrderNo' => $merchantOrderNo,
            'subject' => 'Order '.($order->number ?? $order->id),
            'totalAmount' => [
                'amount' => $amount,
                'currency' => $payment->currency,
            ],
            'paySceneCode' => 'PAYPAGE',
            'paySceneParams' => [
                'redirectUrl' => $redirectUrl,
            ],
            'notifyUrl' => $notifyUrl,
        ];

        try {
            $response = $this->api->placeOrder($bizContent);
        } catch (Throwable $e) {
            throw new GatewayException('Failed to place PayBy order: '.$e->getMessage(), 0, $e);
        }

        $respCode = $response['head']['respCode'] ?? '';
        $body = $response['body'] ?? [];
        $tokenUrl = $body['tokenUrl'] ?? $body['payUrl'] ?? null;

        if ($respCode !== 'SUCCESS' || ! is_string($tokenUrl) || $tokenUrl === '') {
            $msg = $response['head']['respMsg'] ?? 'Could not retrieve payment URL from PayBy.';
            throw new GatewayException("PayBy: {$msg}");
        }

        return new PaymentSession(
            $merchantOrderNo,
            $tokenUrl,
            now()->addMinutes(30),
        );
    }

    public function expireSession(Payment $payment): void
    {
        // PayBy sessions expire automatically on the gateway server
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $merchantOrderNo = (string) $payment->provider_reference;

        if ($merchantOrderNo === '') {
            throw new GatewayException('Payment has no PayBy merchant order reference.');
        }

        try {
            $response = $this->api->queryOrder($merchantOrderNo);
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment status from PayBy: '.$e->getMessage(), 0, $e);
        }

        return $this->buildPaymentUpdate($response, $merchantOrderNo);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $publicKey = $this->settings->paybyPublicKey();

        if ($publicKey !== null && $publicKey !== '') {
            $sign = (string) ($headers['sign'] ?? $headers['x-sign'] ?? '');
            if ($sign === '') {
                throw new InvalidWebhook('PayBy sign header is missing from webhook.');
            }

            if (! $this->signature->verify($payload, $sign, $publicKey)) {
                throw new InvalidWebhook('PayBy RSA signature verification failed.');
            }
        }

        $data = json_decode($payload, true);
        if (! is_array($data) || empty($data)) {
            throw new InvalidWebhook('PayBy webhook payload could not be parsed as JSON.');
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $bizContent = is_array($event['bizContent'] ?? null) ? $event['bizContent'] : $event;
        $merchantOrderNo = (string) ($bizContent['merchantOrderNo'] ?? uniqid('payby_', true));

        // Check if this is a refund event
        if (isset($bizContent['refundOrderNo']) || isset($bizContent['refundMerchantOrderNo'])) {
            $refundId = (string) ($bizContent['refundOrderNo'] ?? $bizContent['refundMerchantOrderNo'] ?? uniqid('ref_', true));
            $amountVal = (string) ($bizContent['refundAmount']['amount'] ?? $bizContent['amount'] ?? '0.00');
            $currency = (string) ($bizContent['refundAmount']['currency'] ?? 'AED');
            $amount = Money::of(number_format((float) $amountVal, 2, '.', ''), $currency);

            $status = match (strtoupper((string) ($bizContent['status'] ?? $bizContent['orderStatus'] ?? ''))) {
                'SUCCESS', 'PAID', 'REFUNDED' => RefundUpdate::SUCCEEDED,
                'PENDING', 'PROCESSING' => RefundUpdate::PENDING,
                default => RefundUpdate::FAILED,
            };

            return new GatewayEvent(
                $refundId,
                'refund.updated',
                null,
                new RefundUpdate(
                    $refundId,
                    $merchantOrderNo,
                    $amount,
                    $status,
                    $event['head']['respMsg'] ?? null,
                ),
            );
        }

        $update = $this->buildPaymentUpdate($event, $merchantOrderNo);

        return new GatewayEvent(
            $merchantOrderNo,
            'payment.updated',
            $update,
            null,
        );
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $merchantOrderNo = (string) $payment->provider_reference;
        if ($merchantOrderNo === '') {
            throw new GatewayException('Cannot refund: PayBy merchant order reference is missing.');
        }

        $refundMerchantOrderNo = 'REF_'.substr($idempotencyKey, 0, 18);
        $amountFloat = (float) $amount->toDisplayDecimal();

        $bizContent = [
            'merchantOrderNo' => $merchantOrderNo,
            'refundMerchantOrderNo' => $refundMerchantOrderNo,
            'refundAmount' => [
                'amount' => $amountFloat,
                'currency' => $amount->currency(),
            ],
            'reason' => 'Refund via Modrix Commerce',
        ];

        try {
            $response = $this->api->refund($bizContent);
        } catch (Throwable $e) {
            throw new GatewayException('PayBy refund request failed: '.$e->getMessage(), 0, $e);
        }

        $head = $response['head'] ?? [];
        $body = $response['body'] ?? [];
        $respCode = strtoupper((string) ($head['respCode'] ?? ''));
        $refundOrderNo = (string) ($body['refundOrderNo'] ?? $refundMerchantOrderNo);

        $status = ($respCode === 'SUCCESS') ? RefundUpdate::SUCCEEDED : RefundUpdate::FAILED;

        return new RefundUpdate(
            $refundOrderNo,
            $merchantOrderNo,
            $amount,
            $status,
            $head['respMsg'] ?? null,
        );
    }

    public function testConnection(): void
    {
        $res = $this->api->testConnection();
        if (! $res['success']) {
            throw new GatewayException($res['error'] ?? 'Connection to PayBy failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function buildPaymentUpdate(array $response, string $merchantOrderNo): PaymentUpdate
    {
        $body = $response['body'] ?? $response['bizContent'] ?? $response;
        $orderStatus = strtoupper((string) ($body['orderStatus'] ?? $body['status'] ?? ''));
        $providerPaymentId = $body['orderNo'] ?? null;
        if (is_string($providerPaymentId)) {
            $providerPaymentId = trim($providerPaymentId);
        }

        $kind = match ($orderStatus) {
            'PAID', 'SUCCESS' => PaymentUpdateKind::Succeeded,
            'CREATED', 'PENDING', 'PROCESSING' => PaymentUpdateKind::Pending,
            default => PaymentUpdateKind::Failed,
        };

        $amount = null;
        $rawAmount = $body['totalAmount']['amount'] ?? $body['amount'] ?? null;
        $currency = $body['totalAmount']['currency'] ?? 'AED';
        if (is_numeric($rawAmount)) {
            $amount = Money::of(number_format((float) $rawAmount, 2, '.', ''), (string) $currency);
        }

        $failureReason = null;
        if ($kind === PaymentUpdateKind::Failed) {
            $failureReason = $response['head']['respMsg'] ?? $body['failReason'] ?? 'Payment failed or was closed on PayBy.';
        }

        return new PaymentUpdate(
            $kind,
            $merchantOrderNo,
            $providerPaymentId,
            $amount,
            $failureReason,
        );
    }
}
