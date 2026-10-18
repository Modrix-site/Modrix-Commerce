<?php

namespace EcoModular\CheckoutCom;

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
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Checkout.com hosted payment page, or Flow embedded on this store.
 *
 * The customer enters payment details on Checkout.com. This store marks an
 * order paid only after a server-side read of the payment shows it captured
 * for the same amount and currency. The browser return is not proof.
 *
 * @see https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-a-hosted-page
 * @see https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-your-website/get-started-with-flow
 */
final class CheckoutComGateway implements PaymentGateway
{
    public const CODE = 'checkout_com';

    /** Hosted payment pages expire after 24 hours. */
    public const SESSION_HOURS = 23;

    public function __construct(
        private readonly CheckoutComSettings $settings,
        private readonly CheckoutComApi $api,
        private readonly CheckoutComPayload $payload,
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
        return 'Checkout.com';
    }

    public function instructions(): string
    {
        return $this->settings->flow()
            ? 'You pay in Checkout.com\'s secure form on this page. Your card details are entered there and are never stored by this store.'
            : 'You will be redirected to Checkout.com\'s secure payment page. Your card details are entered there and are never stored by this store.';
    }

    public function isConfigured(): bool
    {
        return $this->settings->ready();
    }

    public function isAvailable(PricedCart $cart): bool
    {
        try {
            return $cart->grandTotal()->isPositive() && CheckoutComAmount::toMinor($cart->grandTotal()) > 0;
        } catch (GatewayException) {
            return false;
        }
    }

    public function allowsFulfillmentBeforePayment(): bool
    {
        return false;
    }

    public function createSession(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): PaymentSession
    {
        $this->assertEnvironment();
        $body = $this->payload->session($order, $payment, $returnUrl, $cancelUrl);
        $expiresAt = now()->addHours(self::SESSION_HOURS);

        if ($this->settings->flow()) {
            $session = $this->api->post('/payment-sessions', $body, 'ecomodular-payment-'.$payment->id);
            $id = $session['id'] ?? null;

            if (! is_string($id) || ! str_starts_with($id, 'ps_')) {
                throw new GatewayException('Checkout.com did not return a payment session.');
            }

            CheckoutComFlowSession::remember($payment, $session);

            return new PaymentSession($id, route('ecomodular.checkout-com.flow', ['payment' => $payment->id]), $expiresAt);
        }

        $session = $this->api->post('/hosted-payments', $body, 'ecomodular-payment-'.$payment->id);
        $id = $session['id'] ?? null;
        $url = $session['_links']['redirect']['href'] ?? null;

        if (! is_string($id) || ! str_starts_with($id, 'hpp_') || ! is_string($url) || ! CheckoutComEndpoint::isCheckoutHost($url)) {
            throw new GatewayException('Checkout.com did not return a payment page.');
        }

        return new PaymentSession($id, $url, $expiresAt);
    }

    public function expireSession(Payment $payment): void
    {
        // Hosted pages and payment sessions expire at Checkout.com.
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $reference = (string) $payment->provider_reference;

        if (str_starts_with($reference, 'ps_')) {
            $session = $this->api->get('/payment-sessions/'.rawurlencode($reference));
            $paymentId = $session['payment_id'] ?? $session['payment']['id'] ?? null;

            if (! is_string($paymentId) || ! str_starts_with($paymentId, 'pay_')) {
                return new PaymentUpdate(PaymentUpdateKind::Pending, $reference);
            }

            return $this->fromPayment($reference, $this->api->get('/payments/'.rawurlencode($paymentId)));
        }

        if (! str_starts_with($reference, 'hpp_')) {
            throw new GatewayException('Missing Checkout.com payment reference.');
        }

        $hosted = $this->api->get('/hosted-payments/'.rawurlencode($reference));
        $status = (string) ($hosted['status'] ?? '');
        $paymentId = $hosted['payment_id'] ?? null;

        if (is_string($paymentId) && str_starts_with($paymentId, 'pay_')) {
            return $this->fromPayment($reference, $this->api->get('/payments/'.rawurlencode($paymentId)));
        }

        if ($status === 'Expired') {
            return new PaymentUpdate(PaymentUpdateKind::Expired, $reference);
        }

        return new PaymentUpdate(PaymentUpdateKind::Pending, $reference);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $secret = $this->settings->webhookSecret();

        if ($secret === null) {
            throw new InvalidWebhook('Checkout.com webhooks are not configured.');
        }

        $signature = $headers['cko-signature'] ?? '';

        if (! CheckoutComSignature::matches($payload, $secret, $signature)) {
            throw new InvalidWebhook('Checkout.com webhook signature does not match.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null) || ! is_array($event['data'] ?? null)) {
            throw new InvalidWebhook('Checkout.com webhook payload is malformed.');
        }

        $data = $event['data'];

        return [
            'id' => mb_substr($event['id'], 0, 191),
            'type' => $event['type'],
            'payment_id' => is_string($data['id'] ?? null) ? $data['id'] : '',
            'action_id' => is_string($data['action_id'] ?? null) ? $data['action_id'] : '',
            'reference' => is_string($data['reference'] ?? null) ? $data['reference'] : '',
            'amount' => $data['amount'] ?? null,
            'currency' => is_string($data['currency'] ?? null) ? $data['currency'] : '',
        ];
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $id = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if (in_array($type, ['payment_refunded', 'payment_refund_declined'], true)) {
            return new GatewayEvent($id, $type, refund: $this->refundFromEvent($event, $type));
        }

        if (! str_starts_with($type, 'payment_')) {
            return new GatewayEvent($id, $type);
        }

        $payment = $this->paymentFromEvent($event);

        if ($payment === null) {
            return new GatewayEvent($id, $type);
        }

        return new GatewayEvent($id, $type, payment: $this->retrieve($payment));
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $paymentId = $payment->provider_payment_id
            ?? throw new GatewayException('This payment has no Checkout.com payment id and cannot be refunded.');

        $reference = mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', $idempotencyKey) ?? '', 0, 80);
        $response = $this->api->post('/payments/'.rawurlencode($paymentId).'/refunds', [
            'amount' => CheckoutComAmount::toMinor($amount),
            'reference' => $reference !== '' ? $reference : 'refund-'.$payment->id,
        ], $idempotencyKey);

        $actionId = $response['action_id'] ?? null;

        if (! is_string($actionId) || $actionId === '') {
            throw new GatewayException('Checkout.com did not confirm the refund. Check the Checkout.com dashboard before trying again.');
        }

        return new RefundUpdate($actionId, $paymentId, $amount, RefundUpdate::PENDING);
    }

    /**
     * A 401 means the secret key was rejected. A 404 for an unknown page means
     * the key was accepted.
     *
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        $this->assertEnvironment();

        try {
            $response = $this->api->get('/hosted-payments/hpp_abcdefghijklmnopqrstuvwxyz');
        } catch (GatewayException $e) {
            if (preg_match('/unauthor|invalid_authorization|\b40[13]\b/i', $e->getMessage()) === 1) {
                throw new GatewayException('Checkout.com rejected the secret key.');
            }

            throw $e;
        }

        if (($response['_http_status'] ?? null) !== 404 && ! isset($response['id']) && ! isset($response['request_id'])) {
            throw new GatewayException('Checkout.com returned an unexpected response.');
        }
    }

    /**
     * @throws GatewayException
     */
    private function assertEnvironment(): void
    {
        $base = $this->settings->apiBase();
        $secret = $this->settings->secretKey();

        if ($base === null || $secret === null) {
            throw new GatewayException('Checkout.com is not configured.');
        }

        $sandboxKey = str_starts_with($secret, 'sk_sbox_');

        if ($sandboxKey !== CheckoutComEndpoint::isSandboxHost($base)) {
            throw new GatewayException('The Checkout.com secret key and API base URL are for different environments.');
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function fromPayment(string $sessionReference, array $payment): PaymentUpdate
    {
        $id = is_string($payment['id'] ?? null) ? $payment['id'] : null;
        $status = strtolower((string) ($payment['status'] ?? ''));
        $amount = $this->money($payment['amount'] ?? null, $payment['currency'] ?? null);

        $kind = match ($status) {
            'captured', 'partially refunded', 'refunded', 'paid' => PaymentUpdateKind::Succeeded,
            'authorized', 'partially captured' => PaymentUpdateKind::Processing,
            'declined' => PaymentUpdateKind::Failed,
            'canceled', 'voided', 'expired' => PaymentUpdateKind::Expired,
            default => PaymentUpdateKind::Pending,
        };

        return new PaymentUpdate(
            $kind,
            $sessionReference,
            $id,
            $kind === PaymentUpdateKind::Succeeded ? $amount : null,
            $kind === PaymentUpdateKind::Failed ? 'Checkout.com declined this payment.' : null,
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function paymentFromEvent(array $event): ?Payment
    {
        $paymentId = (string) ($event['payment_id'] ?? '');

        if (str_starts_with($paymentId, 'pay_')) {
            $byPayment = Payment::query()->where('provider', self::CODE)->where('provider_payment_id', $paymentId)->latest('id')->first();

            if ($byPayment !== null) {
                return $byPayment;
            }
        }

        $reference = (string) ($event['reference'] ?? '');

        if (preg_match('/^p(\d+)$/', $reference, $match) !== 1) {
            return null;
        }

        $payment = Payment::query()->where('provider', self::CODE)->whereKey((int) $match[1])->first();

        if ($payment === null || $payment->provider_reference === null || $payment->provider_reference === '') {
            throw new GatewayException('Checkout.com webhook arrived before the payment was saved.', retryable: true);
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function refundFromEvent(array $event, string $type): ?RefundUpdate
    {
        $actionId = (string) ($event['action_id'] ?? '');
        $paymentId = (string) ($event['payment_id'] ?? '');
        $amount = $this->money($event['amount'] ?? null, $event['currency'] ?? null);

        if ($actionId === '' || ! str_starts_with($paymentId, 'pay_') || $amount === null || ! $amount->isPositive()) {
            Log::info('Checkout.com refund webhook had no usable action, payment or amount.');

            return null;
        }

        return new RefundUpdate(
            $actionId,
            $paymentId,
            $amount,
            $type === 'payment_refunded' ? RefundUpdate::SUCCEEDED : RefundUpdate::FAILED,
            $type === 'payment_refund_declined' ? 'Checkout.com declined this refund.' : null,
        );
    }

    private function money(mixed $amount, mixed $currency): ?Money
    {
        if (! is_string($currency) || ! is_int($amount) || $amount < 0) {
            return null;
        }

        try {
            return CheckoutComAmount::toMoney($amount, $currency);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
