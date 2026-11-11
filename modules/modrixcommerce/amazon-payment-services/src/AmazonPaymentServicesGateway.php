<?php

namespace EcoModular\AmazonPaymentServices;

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
 * Amazon Payment Services (APS / PayFort) payment gateway.
 *
 * Implements the official hosted checkout, signature validation, server status inquiry,
 * webhook processing, and API refunds for UAE and MENA merchants.
 */
final class AmazonPaymentServicesGateway implements PaymentGateway
{
    public const CODE = 'amazon-payment-services';

    public function __construct(
        private readonly AmazonPaymentServicesSettings $settings,
        private readonly AmazonPaymentServicesApi $api,
        private readonly AmazonPaymentServicesSignature $signature,
        private readonly AmazonPaymentServicesAmount $amountHelper,
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
        return 'You will be redirected to Amazon Payment Services to pay securely with your card, Apple Pay, or regional payment methods.';
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
            throw new GatewayException('Amazon Payment Services is not configured.');
        }

        if (! in_array($payment->currency, $this->settings->currencies(), true)) {
            throw new GatewayException("Amazon Payment Services is not configured for currency {$payment->currency}.");
        }

        $merchantId = $this->settings->merchantIdentifier();
        $accessCode = $this->settings->accessCode();
        $requestPhrase = $this->settings->shaRequestPhrase();

        if ($merchantId === null || $accessCode === null || $requestPhrase === null) {
            throw new GatewayException('Amazon Payment Services merchant credentials are incomplete.');
        }

        $merchantReference = 'APS-'.$payment->id.'-'.substr(hash_hmac('sha256', (string) $payment->id, (string) config('app.key')), 0, 16);
        $amount = (string) $this->amountHelper->toMinor($payment->money());
        $responseUrl = route('modrixcommerce.amazon-payment-services.response');

        $fields = [
            'command' => $this->settings->command(),
            'access_code' => $accessCode,
            'merchant_identifier' => $merchantId,
            'merchant_reference' => $merchantReference,
            'amount' => $amount,
            'currency' => $payment->currency,
            'language' => $this->settings->language(),
            'customer_email' => mb_substr($order->email ?? '', 0, 254),
            'return_url' => $responseUrl,
        ];

        $fields['signature'] = $this->signature->calculate(
            $fields,
            $requestPhrase,
            $this->settings->shaType(),
        );

        $action = $this->api->checkoutUrl();

        Cache::put("aps:handoff:{$payment->id}", [
            'action' => $action,
            'fields' => $fields,
            'merchantReference' => $merchantReference,
        ], now()->addMinutes(30));

        $handoffUrl = route('modrixcommerce.amazon-payment-services.handoff', $payment);

        return new PaymentSession(
            $merchantReference,
            $handoffUrl,
            now()->addMinutes(30),
        );
    }

    public function expireSession(Payment $payment): void
    {
        Cache::forget("aps:handoff:{$payment->id}");
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $sessionRef = (string) $payment->provider_reference;

        try {
            $response = $this->api->checkStatus($sessionRef, $payment->provider_payment_id);
        } catch (Throwable $e) {
            throw new GatewayException('Could not retrieve payment from Amazon Payment Services: '.$e->getMessage());
        }

        return $this->buildPaymentUpdate($response, $sessionRef);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $responsePhrase = $this->settings->shaResponsePhrase();

        if ($responsePhrase === null || $responsePhrase === '') {
            throw new InvalidWebhook('Amazon Payment Services SHA response phrase is not configured.');
        }

        $data = json_decode($payload, true);

        if (! is_array($data)) {
            parse_str($payload, $data);
        }

        if (! is_array($data) || $data === []) {
            throw new InvalidWebhook('Amazon Payment Services payload could not be parsed.');
        }

        $signature = (string) ($data['signature'] ?? '');

        if ($signature === '') {
            throw new InvalidWebhook('Amazon Payment Services response signature is missing.');
        }

        if (! $this->signature->verify($data, $signature, $responsePhrase, $this->settings->shaType())) {
            throw new InvalidWebhook('Amazon Payment Services response signature mismatch.');
        }

        return $data;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $eventId = (string) ($event['fort_id'] ?? $event['merchant_reference'] ?? uniqid('aps_', true));
        $command = strtoupper((string) ($event['command'] ?? ''));

        if ($command === 'REFUND') {
            $amount = isset($event['amount'], $event['currency'])
                ? $this->amountHelper->fromMinor((int) $event['amount'], (string) $event['currency'])
                : Money::zero('AED');

            $status = match (substr((string) ($event['response_code'] ?? ''), 0, 2)) {
                '06', '14' => RefundUpdate::SUCCEEDED,
                '04', '02' => RefundUpdate::PENDING,
                default => RefundUpdate::FAILED,
            };

            return new GatewayEvent(
                $eventId,
                'refund.updated',
                null,
                new RefundUpdate(
                    (string) ($event['fort_id'] ?? $event['merchant_reference'] ?? ''),
                    (string) ($event['fort_id'] ?? null),
                    $amount,
                    $status,
                    $event['response_message'] ?? null,
                ),
            );
        }

        $sessionRef = (string) ($event['merchant_reference'] ?? '');
        $paymentUpdate = $this->buildPaymentUpdate($event, $sessionRef);

        return new GatewayEvent(
            $eventId,
            'payment.updated',
            $paymentUpdate,
            null,
        );
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $fortId = $payment->provider_payment_id
            ?? throw new GatewayException('Cannot refund: PayFort transaction ID is missing.');

        $refundReference = 'APS-REF-'.substr($idempotencyKey, 0, 20);

        try {
            $response = $this->api->refund($refundReference, $fortId, $amount);
        } catch (Throwable $e) {
            throw new GatewayException('Amazon Payment Services refund failed: '.$e->getMessage());
        }

        $code = (string) ($response['response_code'] ?? '');
        $status = match (substr($code, 0, 2)) {
            '06', '14' => RefundUpdate::SUCCEEDED,
            '04', '02' => RefundUpdate::PENDING,
            default => RefundUpdate::FAILED,
        };

        return new RefundUpdate(
            (string) ($response['fort_id'] ?? $refundReference),
            $fortId,
            $amount,
            $status,
            $response['response_message'] ?? null,
        );
    }

    public function testConnection(): void
    {
        $this->api->testConnection();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildPaymentUpdate(array $data, string $sessionReference): PaymentUpdate
    {
        $responseCode = (string) ($data['response_code'] ?? $data['transaction_status'] ?? '');
        $fortId = isset($data['fort_id']) ? (string) $data['fort_id'] : null;
        $reason = isset($data['response_message']) ? (string) $data['response_message'] : null;

        $amount = null;
        if (isset($data['amount'], $data['currency'])) {
            $amount = $this->amountHelper->fromMinor((int) $data['amount'], (string) $data['currency']);
        }

        $kind = match (substr($responseCode, 0, 2)) {
            '14' => PaymentUpdateKind::Succeeded,
            '04' => PaymentUpdateKind::Processing,
            '02' => PaymentUpdateKind::Pending,
            '00', '01' => PaymentUpdateKind::Failed,
            default => PaymentUpdateKind::Failed,
        };

        return new PaymentUpdate(
            $kind,
            $sessionReference,
            $fortId,
            $amount,
            $kind === PaymentUpdateKind::Failed ? ($reason ?? 'Payment rejected by Amazon Payment Services.') : null,
        );
    }
}
