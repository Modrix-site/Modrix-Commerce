<?php

namespace EcoModular\PayU;

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
 * PayU Payment Gateway for India.
 *
 * Supports Cards, UPI, Net Banking, EMI, and Wallets with
 * SHA-512 cryptographic verification and server-side status inquiry via `postservice.php`.
 */
final class PayuGateway implements PaymentGateway
{
    public const CODE = 'payu';

    public function __construct(
        private readonly PayuSettings $settings,
        private readonly PayuApi $api,
        private readonly PayuSignature $signature,
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
        return 'Pay securely using PayU (Credit/Debit Cards, UPI, Net Banking across all major banks, or Wallets).';
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
            throw new GatewayException('PayU Payment Gateway is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("PayU is not configured for currency {$payment->currency}.");
        }

        $key = $this->settings->merchantKey();
        $salt = $this->settings->salt();

        if ($key === null || $salt === null) {
            throw new GatewayException('PayU Merchant Key or Salt is missing.');
        }

        $txnid = 'PAYU_' . $payment->id . '_' . substr(hash_hmac('sha256', (string) $payment->id, (string) config('app.key')), 0, 10);
        $amount = number_format((float) $payment->money()->toDisplayDecimal(), 2, '.', '');
        $productinfo = 'Order ' . ($order->number ?? $order->id);
        $firstname = $order->shippingAddress?->first_name ?? $order->customer?->first_name ?? 'Customer';
        $email = $order->email;
        $phone = $order->phone ?? '';

        $hash = $this->signature->generateRequestHash(
            $key,
            $txnid,
            $amount,
            $productinfo,
            $firstname,
            $email,
            $salt,
        );

        $responseUrl = route('modrixcommerce.payu.response');

        $fields = [
            'key' => $key,
            'txnid' => $txnid,
            'amount' => $amount,
            'productinfo' => $productinfo,
            'firstname' => $firstname,
            'email' => $email,
            'phone' => $phone,
            'surl' => $responseUrl,
            'furl' => $responseUrl,
            'hash' => $hash,
            'service_provider' => 'payu_paisa',
        ];

        $paymentUrl = $this->api->paymentUrl();

        Cache::put("payu:handoff:{$payment->id}", [
            'action' => $paymentUrl,
            'fields' => $fields,
            'txnid' => $txnid,
            'orderNumber' => $order->number,
        ], now()->addMinutes(30));

        $handoffUrl = route('modrixcommerce.payu.handoff', ['payment' => $payment->id]);

        return new PaymentSession(
            $txnid,
            $handoffUrl,
            now()->addMinutes(30),
        );
    }

    public function expireSession(Payment $payment): void
    {
        Cache::forget("payu:handoff:{$payment->id}");
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $txnid = (string) $payment->provider_reference;

        if ($txnid === '') {
            throw new GatewayException('Payment has no PayU transaction reference.');
        }

        try {
            $response = $this->api->verifyPayment($txnid);
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment status from PayU: ' . $e->getMessage(), 0, $e);
        }

        return $this->buildPaymentUpdate($response, $txnid);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $salt = $this->settings->salt();
        if ($salt === null || $salt === '') {
            throw new InvalidWebhook('PayU Salt is not configured.');
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            parse_str($payload, $data);
        }

        if (! is_array($data) || empty($data)) {
            throw new InvalidWebhook('PayU callback payload is empty or invalid.');
        }

        if (! $this->signature->verifyResponseHash($data, $salt)) {
            throw new InvalidWebhook('PayU SHA-512 response hash verification failed.');
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $txnid = (string) ($event['txnid'] ?? uniqid('payu_', true));

        // Check if this is a refund event
        if (isset($event['refundId']) || isset($event['request_id']) || str_contains((string) ($event['command'] ?? ''), 'refund')) {
            $refundId = (string) ($event['refundId'] ?? $event['request_id'] ?? uniqid('payu_ref_', true));
            $mihpayid = (string) ($event['mihpayid'] ?? $txnid);
            $amountVal = (string) ($event['amount'] ?? '0.00');
            $amount = Money::of($amountVal, 'INR');

            $status = match (strtolower((string) ($event['status'] ?? ''))) {
                'success', '1' => RefundUpdate::SUCCEEDED,
                'pending' => RefundUpdate::PENDING,
                default => RefundUpdate::FAILED,
            };

            return new GatewayEvent(
                $refundId,
                'refund.updated',
                null,
                new RefundUpdate(
                    $refundId,
                    $mihpayid !== '' ? $mihpayid : null,
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
        $mihpayid = (string) ($payment->provider_payment_id ?? '');

        if ($mihpayid === '') {
            try {
                $statusData = $this->api->verifyPayment($txnid);
                $details = $statusData['transaction_details'][$txnid] ?? [];
                $mihpayid = (string) ($details['mihpayid'] ?? '');
            } catch (Throwable $e) {
                throw new GatewayException('Failed to discover PayU mihpayid for refund: ' . $e->getMessage(), 0, $e);
            }
        }

        if ($mihpayid === '') {
            throw new GatewayException('No PayU mihpayid found for refund.');
        }

        $token = 'REF_' . substr($idempotencyKey, 0, 18);
        $refundAmount = number_format((float) $amount->toDisplayDecimal(), 2, '.', '');

        try {
            $response = $this->api->refund($mihpayid, $token, $refundAmount);
        } catch (Throwable $e) {
            throw new GatewayException('PayU refund request failed: ' . $e->getMessage(), 0, $e);
        }

        $statusNum = $response['status'] ?? 0;
        $status = ((int) $statusNum === 1) ? RefundUpdate::SUCCEEDED : RefundUpdate::FAILED;
        $refundId = (string) ($response['request_id'] ?? $token);

        return new RefundUpdate(
            $refundId,
            $mihpayid,
            $amount,
            $status,
            $response['msg'] ?? null,
        );
    }

    public function testConnection(): void
    {
        $res = $this->api->testConnection();
        if (! $res['success']) {
            throw new GatewayException($res['error'] ?? 'Connection to PayU failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function buildPaymentUpdate(array $response, string $txnid): PaymentUpdate
    {
        // Response can be direct callback array OR result from verify_payment API
        $details = $response['transaction_details'][$txnid] ?? $response;

        $status = strtolower((string) ($details['status'] ?? ''));
        $providerPaymentId = $details['mihpayid'] ?? null;
        if (is_string($providerPaymentId) || is_numeric($providerPaymentId)) {
            $providerPaymentId = trim((string) $providerPaymentId);
        } else {
            $providerPaymentId = null;
        }

        $kind = match ($status) {
            'success' => PaymentUpdateKind::Succeeded,
            'pending', 'in progress' => PaymentUpdateKind::Pending,
            default => PaymentUpdateKind::Failed,
        };

        $amount = null;
        $rawAmount = $details['amount'] ?? null;
        if (is_numeric($rawAmount)) {
            $amount = Money::of(number_format((float) $rawAmount, 2, '.', ''), 'INR');
        }

        $failureReason = null;
        if ($kind === PaymentUpdateKind::Failed) {
            $failureReason = $details['error_Message'] ?? $details['error_code'] ?? $details['msg'] ?? 'Payment was declined or failed on PayU.';
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
