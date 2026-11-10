<?php

namespace EcoModular\Paypal;

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
 * PayPal Checkout (Orders v2). The customer approves on PayPal. This store
 * captures the order on the server and marks it paid only when the capture
 * is completed for the same amount. The browser return is not proof.
 *
 * @see https://developer.paypal.com/api/rest/integration/orders-api
 * @see https://developer.paypal.com/docs/api/orders/v2/
 */
final class PaypalGateway implements PaymentGateway
{
    public const CODE = 'paypal';

    /** PayPal approval links last about 3 hours. */
    public const SESSION_MINUTES = 165;

    public function __construct(
        private readonly PaypalSettings $settings,
        private readonly PaypalApi $api,
        private readonly PaypalPayload $payload,
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
        return 'PayPal';
    }

    public function instructions(): string
    {
        return 'You will be redirected to PayPal to approve this payment. Card details are entered there and are never stored by this store.';
    }

    public function isConfigured(): bool
    {
        return $this->settings->ready();
    }

    public function isAvailable(PricedCart $cart): bool
    {
        try {
            return $cart->grandTotal()->isPositive() && PaypalAmount::toValue($cart->grandTotal()) !== '0' && PaypalAmount::toValue($cart->grandTotal()) !== '0.00';
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
        $created = $this->api->post(
            '/v2/checkout/orders',
            $this->payload->order($order, $payment, $returnUrl, $cancelUrl),
            'ecomodular-payment-'.$payment->id,
        );

        $id = $created['id'] ?? null;
        $url = $this->approvalUrl($created);

        if (! is_string($id) || preg_match('/^[A-Z0-9]{10,36}$/', $id) !== 1 || $url === null) {
            throw new GatewayException('PayPal did not return a checkout page.');
        }

        return new PaymentSession($id, $url, now()->addMinutes(self::SESSION_MINUTES));
    }

    public function expireSession(Payment $payment): void
    {
        // An unapproved PayPal order expires on its own.
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $reference = (string) $payment->provider_reference;

        if (preg_match('/^[A-Z0-9]{10,36}$/', $reference) !== 1) {
            throw new GatewayException('Missing PayPal order id.');
        }

        $order = $this->api->get('/v2/checkout/orders/'.rawurlencode($reference));

        if (($order['status'] ?? null) === 'APPROVED') {
            $order = $this->capture($reference, $payment);
        }

        return $this->fromOrder($reference, $order);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $webhookId = $this->settings->webhookId() ?? throw new InvalidWebhook('PayPal webhooks are not configured.');
        $certUrl = $headers['paypal-cert-url'] ?? '';

        foreach (['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'] as $header) {
            if (($headers[$header] ?? '') === '') {
                throw new InvalidWebhook('PayPal webhook is missing a signature header.');
            }
        }

        if (! PaypalEndpoint::isCertificateUrl($certUrl)) {
            throw new InvalidWebhook('PayPal webhook certificate URL is not from PayPal.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['event_type'] ?? null) || ! is_array($event['resource'] ?? null)) {
            throw new InvalidWebhook('PayPal webhook payload is malformed.');
        }

        try {
            $verification = $this->api->verifySignature([
                'auth_algo' => $headers['paypal-auth-algo'],
                'cert_url' => $certUrl,
                'transmission_id' => $headers['paypal-transmission-id'],
                'transmission_sig' => $headers['paypal-transmission-sig'],
                'transmission_time' => $headers['paypal-transmission-time'],
                'webhook_id' => $webhookId,
                'webhook_event' => $event,
            ]);
        } catch (GatewayException $e) {
            if ($e->retryable) {
                throw $e;
            }

            throw new InvalidWebhook($e->getMessage());
        }

        if (! $this->api->signatureVerified($verification)) {
            throw new InvalidWebhook('PayPal webhook signature does not match.');
        }

        return [
            'id' => mb_substr($event['id'], 0, 191),
            'type' => $event['event_type'],
            'resource' => $event['resource'],
        ];
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $id = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if ($type === 'PAYMENT.CAPTURE.REFUNDED') {
            return new GatewayEvent($id, $type, refund: $this->refundFromEvent($event));
        }

        if (! in_array($type, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.PENDING'], true)) {
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
        $captureId = $payment->provider_payment_id
            ?? throw new GatewayException('This payment has no PayPal capture id and cannot be refunded.');

        $response = $this->api->post('/v2/payments/captures/'.rawurlencode($captureId).'/refund', [
            'amount' => [
                'currency_code' => $amount->currency(),
                'value' => PaypalAmount::toValue($amount),
            ],
        ], $idempotencyKey);

        $refundId = $response['id'] ?? null;
        $status = (string) ($response['status'] ?? '');

        if (! is_string($refundId) || $refundId === '') {
            throw new GatewayException('PayPal did not confirm the refund. Check the PayPal dashboard before trying again.');
        }

        return new RefundUpdate(
            $refundId,
            $captureId,
            $amount,
            $status === 'COMPLETED' ? RefundUpdate::SUCCEEDED : ($status === 'PENDING' ? RefundUpdate::PENDING : RefundUpdate::FAILED),
            $status === 'CANCELLED' ? 'PayPal cancelled this refund.' : null,
        );
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function fromOrder(string $reference, array $order): PaymentUpdate
    {
        $status = (string) ($order['status'] ?? '');

        if (in_array($status, ['CREATED', 'PAYER_ACTION_REQUIRED', 'APPROVED'], true)) {
            return new PaymentUpdate(PaymentUpdateKind::Pending, $reference);
        }

        if ($status === 'VOIDED') {
            return new PaymentUpdate(PaymentUpdateKind::Expired, $reference);
        }

        $capture = $this->captureRecord($order);

        if ($capture === null) {
            return new PaymentUpdate($status === 'COMPLETED' ? PaymentUpdateKind::Processing : PaymentUpdateKind::Pending, $reference);
        }

        $captureStatus = (string) ($capture['status'] ?? '');
        $id = is_string($capture['id'] ?? null) ? $capture['id'] : null;
        $amount = $this->money($capture['amount']['value'] ?? null, $capture['amount']['currency_code'] ?? null);

        $kind = match ($captureStatus) {
            'COMPLETED', 'PARTIALLY_REFUNDED', 'REFUNDED' => PaymentUpdateKind::Succeeded,
            'PENDING' => PaymentUpdateKind::Processing,
            'DECLINED', 'FAILED' => PaymentUpdateKind::Failed,
            default => PaymentUpdateKind::Pending,
        };

        return new PaymentUpdate(
            $kind,
            $reference,
            $id,
            $kind === PaymentUpdateKind::Succeeded ? $amount : null,
            $kind === PaymentUpdateKind::Failed ? 'PayPal declined this payment.' : null,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function capture(string $reference, Payment $payment): array
    {
        try {
            return $this->api->post('/v2/checkout/orders/'.rawurlencode($reference).'/capture', [], 'ecomodular-capture-'.$payment->id);
        } catch (GatewayException $e) {
            if (str_contains($e->getMessage(), 'ORDER_ALREADY_CAPTURED')) {
                return $this->api->get('/v2/checkout/orders/'.rawurlencode($reference));
            }

            if (str_contains($e->getMessage(), 'ORDER_NOT_APPROVED')) {
                return ['id' => $reference, 'status' => 'CREATED'];
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $created
     */
    private function approvalUrl(array $created): ?string
    {
        $links = $created['links'] ?? [];

        if (! is_array($links)) {
            return null;
        }

        foreach (['payer-action', 'approve'] as $rel) {
            foreach ($links as $link) {
                if (is_array($link) && ($link['rel'] ?? null) === $rel && is_string($link['href'] ?? null) && PaypalEndpoint::isApprovalUrl($link['href'])) {
                    return $link['href'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>|null
     */
    private function captureRecord(array $order): ?array
    {
        $found = null;

        foreach ($order['purchase_units'] ?? [] as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            foreach ($unit['payments']['captures'] ?? [] as $capture) {
                if (! is_array($capture)) {
                    continue;
                }

                $found ??= $capture;

                if (($capture['status'] ?? null) === 'COMPLETED') {
                    return $capture;
                }
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function paymentFromEvent(array $event): ?Payment
    {
        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        if (! is_string($orderId) && str_starts_with((string) ($event['type'] ?? ''), 'CHECKOUT.ORDER')) {
            $orderId = $resource['id'] ?? null;
        }

        if (is_string($orderId) && $orderId !== '') {
            $payment = Payment::query()->where('provider', self::CODE)->where('provider_reference', $orderId)->latest('id')->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        $custom = $resource['custom_id'] ?? ($resource['purchase_units'][0]['custom_id'] ?? null);

        if (! is_string($custom) || preg_match('/^p(\d+)$/', $custom, $match) !== 1) {
            return null;
        }

        $payment = Payment::query()->where('provider', self::CODE)->whereKey((int) $match[1])->first();

        if ($payment === null || $payment->provider_reference === null || $payment->provider_reference === '') {
            throw new GatewayException('PayPal webhook arrived before the payment was saved.', retryable: true);
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function refundFromEvent(array $event): ?RefundUpdate
    {
        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];
        $refundId = is_string($resource['id'] ?? null) ? $resource['id'] : '';
        $amount = $this->money($resource['amount']['value'] ?? null, $resource['amount']['currency_code'] ?? null);
        $captureId = $this->captureIdFromLinks($resource['links'] ?? []);

        if ($refundId === '' || $captureId === null || $amount === null || ! $amount->isPositive()) {
            Log::info('PayPal refund webhook had no usable refund, capture or amount.');

            return null;
        }

        $status = (string) ($resource['status'] ?? '');

        return new RefundUpdate(
            $refundId,
            $captureId,
            $amount,
            $status === 'COMPLETED' ? RefundUpdate::SUCCEEDED : RefundUpdate::PENDING,
        );
    }

    private function captureIdFromLinks(mixed $links): ?string
    {
        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            $href = is_array($link) ? ($link['href'] ?? null) : null;

            if (is_string($href) && preg_match('#/captures/([A-Z0-9]{10,36})(?:\?|$)#', $href, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    private function money(mixed $value, mixed $currency): ?Money
    {
        if (! is_string($value) || ! is_string($currency) || preg_match('/^\d+(\.\d+)?$/', $value) !== 1) {
            return null;
        }

        try {
            return PaypalAmount::toMoney($value, $currency);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
