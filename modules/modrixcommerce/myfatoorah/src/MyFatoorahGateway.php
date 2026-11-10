<?php

namespace EcoModular\MyFatoorah;

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
 * MyFatoorah Payment Gateway for UAE & GCC.
 *
 * Supports Cards, Apple Pay, Google Pay, Mada, KNET, and Benefit with
 * HMAC-SHA256 signature verification and server-to-server status inquiry and refunds.
 */
final class MyFatoorahGateway implements PaymentGateway
{
    public const CODE = 'myfatoorah';

    public function __construct(
        private readonly MyFatoorahSettings $settings,
        private readonly MyFatoorahApi $api,
        private readonly MyFatoorahSignature $signature,
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
        return 'Pay securely using MyFatoorah (Credit/Debit Cards, Apple Pay, Google Pay, Mada, or KNET).';
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
            throw new GatewayException('MyFatoorah Payment Gateway is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("MyFatoorah is not configured for currency {$payment->currency}.");
        }

        $customerRef = 'MF_' . $payment->id . '_' . substr(hash_hmac('sha256', (string) $payment->id, (string) config('app.key')), 0, 10);
        $amount = (float) $payment->money()->toDisplayDecimal();
        $responseUrl = route('modrixcommerce.myfatoorah.response');

        $mobile = preg_replace('/[^0-9]/', '', (string) ($order->phone ?? '500000000'));
        if (strlen($mobile) < 8) {
            $mobile = '500000000';
        }

        $payload = [
            'PaymentMethodId' => 0,
            'InvoiceValue' => $amount,
            'DisplayCurrencyIso' => $payment->currency,
            'CustomerName' => mb_substr($order->shippingAddress?->first_name ?? $order->customer?->first_name ?? 'Customer', 0, 100),
            'CustomerEmail' => $order->email,
            'CustomerMobile' => $mobile,
            'CustomerReference' => $customerRef,
            'CallBackUrl' => $responseUrl,
            'ErrorUrl' => $responseUrl,
            'Language' => 'en',
        ];

        try {
            $response = $this->api->executePayment($payload);
        } catch (Throwable $e) {
            throw new GatewayException('Failed to initiate MyFatoorah payment: ' . $e->getMessage(), 0, $e);
        }

        $data = $response['Data'] ?? [];
        $invoiceId = (string) ($data['InvoiceId'] ?? '');
        $paymentUrl = (string) ($data['PaymentURL'] ?? '');

        if ($paymentUrl === '') {
            throw new GatewayException('MyFatoorah did not return a valid payment URL.');
        }

        Cache::put("myfatoorah:session:{$customerRef}", [
            'paymentId' => $payment->id,
            'orderId' => $order->id,
            'invoiceId' => $invoiceId,
            'amount' => (string) $amount,
            'currency' => $payment->currency,
        ], now()->addMinutes(30));

        Cache::put("myfatoorah:handoff:{$payment->id}", [
            'checkoutUrl' => $paymentUrl,
            'customerRef' => $customerRef,
            'invoiceId' => $invoiceId,
            'orderNumber' => $order->number ?? (string) $order->id,
        ], now()->addMinutes(30));

        return new PaymentSession(
            $customerRef,
            $paymentUrl,
            now()->addMinutes(30),
        );
    }

    public function expireSession(Payment $payment): void
    {
        Cache::forget("myfatoorah:handoff:{$payment->id}");
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $customerRef = (string) $payment->provider_reference;

        if ($customerRef === '') {
            throw new GatewayException('Payment has no MyFatoorah transaction reference.');
        }

        $session = Cache::get("myfatoorah:session:{$customerRef}", []);
        $invoiceId = $session['invoiceId'] ?? null;

        try {
            if ($invoiceId !== null && $invoiceId !== '') {
                $response = $this->api->getPaymentStatus((string) $invoiceId, 'InvoiceId');
            } elseif ($payment->provider_payment_id !== null && $payment->provider_payment_id !== '') {
                $response = $this->api->getPaymentStatus($payment->provider_payment_id, 'PaymentId');
            } else {
                $response = $this->api->getPaymentStatus($customerRef, 'InvoiceId');
            }
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment status from MyFatoorah: ' . $e->getMessage(), 0, $e);
        }

        return $this->buildPaymentUpdate($response, $customerRef);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $secret = $this->settings->webhookSecret();
        if ($secret !== null && $secret !== '') {
            $sigHeader = $headers['myfatoorah-signature'][0]
                ?? $headers['MyFatoorah-Signature'][0]
                ?? $headers['myfatoorah-signature']
                ?? $headers['MyFatoorah-Signature']
                ?? null;

            if ($sigHeader === null || ! $this->signature->verifyWebhookSignature($payload, (string) $sigHeader, $secret)) {
                throw new InvalidWebhook('MyFatoorah webhook signature verification failed.');
            }
        }

        $data = json_decode($payload, true);
        if (! is_array($data) || empty($data)) {
            throw new InvalidWebhook('MyFatoorah callback payload is empty or invalid.');
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $data = $event['Data'] ?? $event;
        $customerRef = (string) ($data['CustomerReference'] ?? $data['InvoiceId'] ?? uniqid('mf_', true));

        // Check if this is a refund event
        if (isset($data['RefundId']) || isset($event['RefundId']) || str_contains(strtolower((string) ($event['Event'] ?? '')), 'refund')) {
            $refundId = (string) ($data['RefundId'] ?? $event['RefundId'] ?? uniqid('mf_ref_', true));
            $paymentId = (string) ($data['PaymentId'] ?? $data['InvoiceId'] ?? $customerRef);
            $amountVal = (string) ($data['Amount'] ?? $data['RefundAmount'] ?? '0.00');
            $currency = (string) ($data['Currency'] ?? $data['CurrencyIso'] ?? 'AED');
            $amount = Money::of($amountVal, $currency);

            $status = match (strtolower((string) ($data['RefundStatus'] ?? $data['Status'] ?? ''))) {
                'success', 'succss', 'completed' => RefundUpdate::SUCCEEDED,
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
                    $data['Message'] ?? null,
                ),
            );
        }

        $update = $this->buildPaymentUpdate($event, $customerRef);

        return new GatewayEvent(
            $customerRef,
            'payment.updated',
            $update,
            null,
        );
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $customerRef = (string) $payment->provider_reference;
        $session = Cache::get("myfatoorah:session:{$customerRef}", []);
        $key = (string) ($payment->provider_payment_id ?? $session['invoiceId'] ?? $customerRef);
        $keyType = ($payment->provider_payment_id !== null && $payment->provider_payment_id !== '') ? 'PaymentId' : 'InvoiceId';

        $refundAmount = (float) $amount->toDisplayDecimal();

        try {
            $response = $this->api->makeRefund($key, $refundAmount, $payment->currency, 'Refund ' . $idempotencyKey, $keyType);
        } catch (Throwable $e) {
            throw new GatewayException('MyFatoorah refund request failed: ' . $e->getMessage(), 0, $e);
        }

        $data = $response['Data'] ?? [];
        $refundId = (string) ($data['RefundId'] ?? $idempotencyKey);
        $refundStatus = strtolower((string) ($data['RefundStatus'] ?? ''));

        $status = match ($refundStatus) {
            'success', 'succss', 'completed' => RefundUpdate::SUCCEEDED,
            'pending' => RefundUpdate::PENDING,
            default => RefundUpdate::FAILED,
        };

        return new RefundUpdate(
            $refundId,
            $payment->provider_payment_id,
            $amount,
            $status,
            $response['Message'] ?? null,
        );
    }

    public function testConnection(): void
    {
        $res = $this->api->testConnection();
        if (! $res['success']) {
            throw new GatewayException($res['error'] ?? 'Connection to MyFatoorah failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function buildPaymentUpdate(array $response, string $reference): PaymentUpdate
    {
        $data = $response['Data'] ?? $response;
        $invoiceStatus = strtolower((string) ($data['InvoiceStatus'] ?? ''));

        $transactions = (array) ($data['InvoiceTransactions'] ?? []);
        $firstTx = ! empty($transactions) && is_array($transactions[0]) ? $transactions[0] : [];

        $txStatus = strtolower((string) ($firstTx['TransactionStatus'] ?? ''));
        $providerPaymentId = $firstTx['PaymentId'] ?? $firstTx['TransactionId'] ?? $data['PaymentId'] ?? $data['InvoiceId'] ?? null;
        if (is_numeric($providerPaymentId) || is_string($providerPaymentId)) {
            $providerPaymentId = trim((string) $providerPaymentId);
        } else {
            $providerPaymentId = null;
        }

        $kind = match (true) {
            $invoiceStatus === 'paid' || $txStatus === 'succss' || $txStatus === 'success' => PaymentUpdateKind::Succeeded,
            $invoiceStatus === 'pending' || $txStatus === 'pending' => PaymentUpdateKind::Pending,
            default => PaymentUpdateKind::Failed,
        };

        $amount = null;
        $rawAmount = $firstTx['TransactionValue'] ?? $data['InvoiceValue'] ?? null;
        $currency = $firstTx['PaidCurrency'] ?? $data['DisplayCurrencyIso'] ?? 'AED';

        if (is_numeric($rawAmount)) {
            $amount = Money::of(number_format((float) $rawAmount, 2, '.', ''), (string) $currency);
        }

        $failureReason = null;
        if ($kind === PaymentUpdateKind::Failed) {
            $failureReason = $firstTx['Error'] ?? $data['Error'] ?? $response['Message'] ?? 'Payment failed on MyFatoorah.';
            if (is_array($failureReason)) {
                $failureReason = json_encode($failureReason);
            }
        }

        return new PaymentUpdate(
            $kind,
            $reference,
            $providerPaymentId,
            $amount,
            $failureReason,
        );
    }
}
