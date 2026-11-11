<?php

namespace EcoModular\Easebuzz;

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
 * Easebuzz Payment Gateway for India.
 *
 * Supports Credit/Debit Cards, UPI, Net Banking, EMI, and Wallets with
 * SHA-512 cryptographic verification and server-side status inquiry and refunds.
 */
final class EasebuzzGateway implements PaymentGateway
{
    public const CODE = 'easebuzz';

    public function __construct(
        private readonly EasebuzzSettings $settings,
        private readonly EasebuzzApi $api,
        private readonly EasebuzzSignature $signature,
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
        return 'Pay securely using Easebuzz (Credit/Debit Cards, UPI, Net Banking across all major banks, or Wallets).';
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
            throw new GatewayException('Easebuzz Payment Gateway is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("Easebuzz is not configured for currency {$payment->currency}.");
        }

        $key = $this->settings->merchantKey();
        $salt = $this->settings->salt();

        if ($key === null || $salt === null) {
            throw new GatewayException('Easebuzz Merchant Key or Salt is missing.');
        }

        $txnid = 'EB_'.$payment->id.'_'.substr(hash_hmac('sha256', (string) $payment->id, (string) config('app.key')), 0, 10);
        $amount = number_format((float) $payment->money()->toDisplayDecimal(), 2, '.', '');
        $productinfo = 'Order '.($order->number ?? $order->id);
        $firstname = $order->shippingAddress?->first_name ?? $order->customer?->first_name ?? 'Customer';
        $email = $order->email;
        $phone = preg_replace('/[^0-9]/', '', (string) ($order->phone ?? ''));
        if (strlen($phone) < 10) {
            $phone = '9999999999';
        }

        $responseUrl = route('modrixcommerce.easebuzz.response');

        $params = [
            'txnid' => $txnid,
            'amount' => $amount,
            'productinfo' => $productinfo,
            'firstname' => $firstname,
            'email' => $email,
            'phone' => $phone,
            'surl' => $responseUrl,
            'furl' => $responseUrl,
            'udf1' => (string) $payment->id,
            'udf2' => (string) $order->id,
        ];

        try {
            $response = $this->api->initiatePayment($params);
        } catch (Throwable $e) {
            throw new GatewayException('Failed to initiate Easebuzz payment: '.$e->getMessage(), 0, $e);
        }

        $accessKey = (string) ($response['data'] ?? '');
        if ($accessKey === '') {
            throw new GatewayException('Easebuzz did not return an access key or payment URL.');
        }

        $checkoutUrl = $this->api->checkoutUrl($accessKey);

        Cache::put("easebuzz:session:{$txnid}", [
            'paymentId' => $payment->id,
            'orderId' => $order->id,
            'amount' => $amount,
            'email' => $email,
            'phone' => $phone,
        ], now()->addMinutes(30));

        Cache::put("easebuzz:handoff:{$payment->id}", [
            'checkoutUrl' => $checkoutUrl,
            'txnid' => $txnid,
            'orderNumber' => $order->number ?? (string) $order->id,
        ], now()->addMinutes(30));

        return new PaymentSession(
            $txnid,
            $checkoutUrl,
            now()->addMinutes(30),
        );
    }

    public function expireSession(Payment $payment): void
    {
        Cache::forget("easebuzz:handoff:{$payment->id}");
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $txnid = (string) $payment->provider_reference;

        if ($txnid === '') {
            throw new GatewayException('Payment has no Easebuzz transaction reference.');
        }

        $amount = number_format((float) $payment->money()->toDisplayDecimal(), 2, '.', '');
        $sessionData = Cache::get("easebuzz:session:{$txnid}", []);
        $email = (string) ($sessionData['email'] ?? $payment->order?->email ?? '');
        $phone = (string) ($sessionData['phone'] ?? $payment->order?->phone ?? '9999999999');

        try {
            $response = $this->api->retrieveTransaction($txnid, $amount, $email, $phone);
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment status from Easebuzz: '.$e->getMessage(), 0, $e);
        }

        return $this->buildPaymentUpdate($response, $txnid);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $salt = $this->settings->salt();
        if ($salt === null || $salt === '') {
            throw new InvalidWebhook('Easebuzz Salt is not configured.');
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            parse_str($payload, $data);
        }

        if (! is_array($data) || empty($data)) {
            throw new InvalidWebhook('Easebuzz callback payload is empty or invalid.');
        }

        if (! $this->signature->verifyResponseHash($data, $salt)) {
            throw new InvalidWebhook('Easebuzz SHA-512 response hash verification failed.');
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $txnid = (string) ($event['txnid'] ?? uniqid('eb_', true));

        // Check if this is a refund event
        if (isset($event['refund_id']) || isset($event['refundId']) || str_contains((string) ($event['command'] ?? ''), 'refund')) {
            $refundId = (string) ($event['refund_id'] ?? $event['refundId'] ?? uniqid('eb_ref_', true));
            $easepayid = (string) ($event['easepayid'] ?? $txnid);
            $amountVal = (string) ($event['refund_amount'] ?? $event['amount'] ?? '0.00');
            $amount = Money::of($amountVal, 'INR');

            $status = match (strtolower((string) ($event['status'] ?? ''))) {
                'success', 'true', '1' => RefundUpdate::SUCCEEDED,
                'pending' => RefundUpdate::PENDING,
                default => RefundUpdate::FAILED,
            };

            return new GatewayEvent(
                $refundId,
                'refund.updated',
                null,
                new RefundUpdate(
                    $refundId,
                    $easepayid !== '' ? $easepayid : null,
                    $amount,
                    $status,
                    $event['msg'] ?? null,
                ),
            );
        }

        $update = $this->buildPaymentUpdate($event, $txnid);

        return new GatewayEvent(
            $txnid,
            'payment.updated',
            $update,
            null,
        );
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $txnid = (string) $payment->provider_reference;
        $originalAmount = number_format((float) $payment->money()->toDisplayDecimal(), 2, '.', '');
        $refundAmount = number_format((float) $amount->toDisplayDecimal(), 2, '.', '');

        $sessionData = Cache::get("easebuzz:session:{$txnid}", []);
        $email = (string) ($sessionData['email'] ?? $payment->order?->email ?? '');
        $phone = (string) ($sessionData['phone'] ?? $payment->order?->phone ?? '9999999999');

        try {
            $response = $this->api->refund($txnid, $phone, $email, $originalAmount, $refundAmount);
        } catch (Throwable $e) {
            throw new GatewayException('Easebuzz refund request failed: '.$e->getMessage(), 0, $e);
        }

        $status = ($response['status'] ?? false) ? RefundUpdate::SUCCEEDED : RefundUpdate::FAILED;
        $refundId = (string) ($response['refund_id'] ?? $idempotencyKey);
        $providerPaymentId = $payment->provider_payment_id;

        return new RefundUpdate(
            $refundId,
            $providerPaymentId,
            $amount,
            $status,
            $response['msg'] ?? null,
        );
    }

    public function testConnection(): void
    {
        $res = $this->api->testConnection();
        if (! $res['success']) {
            throw new GatewayException($res['error'] ?? 'Connection to Easebuzz failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function buildPaymentUpdate(array $response, string $txnid): PaymentUpdate
    {
        $details = $response['msg'] ?? $response['data'] ?? $response;
        if (! is_array($details)) {
            $details = $response;
        }

        $status = strtolower((string) ($details['status'] ?? ''));
        $providerPaymentId = $details['easepayid'] ?? null;
        if (is_string($providerPaymentId) || is_numeric($providerPaymentId)) {
            $providerPaymentId = trim((string) $providerPaymentId);
        } else {
            $providerPaymentId = null;
        }

        $kind = match ($status) {
            'success' => PaymentUpdateKind::Succeeded,
            'pending', 'initiated' => PaymentUpdateKind::Pending,
            default => PaymentUpdateKind::Failed,
        };

        $amount = null;
        $rawAmount = $details['amount'] ?? null;
        if (is_numeric($rawAmount)) {
            $amount = Money::of(number_format((float) $rawAmount, 2, '.', ''), 'INR');
        }

        $failureReason = null;
        if ($kind === PaymentUpdateKind::Failed) {
            $failureReason = $details['error_Message'] ?? $details['error'] ?? $details['msg'] ?? 'Payment failed on Easebuzz.';
            if (is_array($failureReason)) {
                $failureReason = json_encode($failureReason);
            }
        }

        return new PaymentUpdate(
            $kind,
            $txnid,
            $providerPaymentId,
            $amount,
            $failureReason,
        );
    }
}
