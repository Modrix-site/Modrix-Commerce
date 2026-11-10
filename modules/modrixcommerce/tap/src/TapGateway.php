<?php

namespace EcoModular\Tap;

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
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Tap hosted charges. The customer pays on Tap's page (or the local method
 * Tap redirects to). This store marks the order paid only after Tap reports
 * the charge as CAPTURED for the same amount. The browser return is not proof.
 *
 * @see https://developers.tap.company/reference/charges
 * @see https://developers.tap.company/docs/redirect
 * @see https://developers.tap.company/docs/webhook
 */
final class TapGateway implements PaymentGateway
{
    public const CODE = 'tap';

    /** A Tap charge page lasts 30 minutes unless a shorter expiry is set. */
    public const SESSION_MINUTES = 28;

    private const CHARGE_ID = '/^chg_[A-Za-z0-9]{6,80}$/';

    private const REFUND_ID = '/^re_[A-Za-z0-9]{6,80}$/';

    public function __construct(
        private readonly TapSettings $settings,
        private readonly TapApi $api,
        private readonly TapPayload $payload,
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
        return 'Tap Payments';
    }

    public function instructions(): string
    {
        return 'You will be redirected to Tap to complete this payment. Card details are entered there and are never stored by this store.';
    }

    public function isConfigured(): bool
    {
        return $this->settings->ready();
    }

    public function isAvailable(PricedCart $cart): bool
    {
        try {
            $value = TapAmount::toValue($cart->grandTotal());

            return $cart->grandTotal()->isPositive() && $value !== '0.00' && $value !== '0.000';
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
        $created = $this->api->post('/v2/charges', $this->payload->charge(
            $order,
            $payment,
            $returnUrl,
            route('webhooks.payments', self::CODE),
        ));

        $id = $created['id'] ?? null;
        $url = is_array($created['transaction'] ?? null) ? ($created['transaction']['url'] ?? null) : null;

        if (! is_string($id) || preg_match(self::CHARGE_ID, $id) !== 1 || ! is_string($url) || ! TapEndpoint::isPaymentUrl($url)) {
            throw new GatewayException('Tap did not return a checkout page.');
        }

        return new PaymentSession($id, $url, now()->addMinutes(self::SESSION_MINUTES));
    }

    public function expireSession(Payment $payment): void
    {
        // An unpaid Tap charge expires on its own.
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $reference = (string) $payment->provider_reference;

        if (preg_match(self::CHARGE_ID, $reference) !== 1) {
            throw new GatewayException('Missing Tap charge id.');
        }

        return $this->fromCharge($reference, $this->api->get('/v2/charges/'.rawurlencode($reference)));
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $secret = $this->settings->secret() ?? throw new InvalidWebhook('Tap webhooks are not configured.');
        $hash = $headers['hashstring'] ?? '';
        $event = json_decode($payload, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['status'] ?? null)) {
            throw new InvalidWebhook('Tap webhook payload is malformed.');
        }

        if (! TapSignature::matches($event, $hash, $secret)) {
            throw new InvalidWebhook('Tap webhook signature does not match.');
        }

        $id = $event['id'];

        if (preg_match(self::CHARGE_ID, $id) !== 1 && preg_match(self::REFUND_ID, $id) !== 1) {
            throw new InvalidWebhook('Tap webhook id is not a charge or refund.');
        }

        $object = is_string($event['object'] ?? null) && $event['object'] !== ''
            ? $event['object']
            : (str_starts_with($id, 're_') ? 'refund' : 'charge');

        $event['id'] = $id;
        $event['type'] = $object.'.'.$event['status'];

        return $event;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $id = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if (str_starts_with($type, 'refund') || preg_match(self::REFUND_ID, $id) === 1) {
            return new GatewayEvent($id, $type, refund: $this->refundFromEvent($id));
        }

        if (preg_match(self::CHARGE_ID, $id) !== 1) {
            return new GatewayEvent($id, $type);
        }

        $payment = Payment::query()->where('provider', self::CODE)->where('provider_reference', $id)->latest('id')->first();

        if ($payment === null) {
            return new GatewayEvent($id, $type);
        }

        return new GatewayEvent($id, $type, payment: $this->retrieve($payment));
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $chargeId = $payment->provider_payment_id
            ?? throw new GatewayException('This payment has no Tap charge id and cannot be refunded.');

        if (preg_match(self::CHARGE_ID, $chargeId) !== 1) {
            throw new GatewayException('This payment has no Tap charge id and cannot be refunded.');
        }

        $cacheKey = 'tap:refund:'.hash('sha256', $idempotencyKey);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && preg_match(self::REFUND_ID, $cached) === 1) {
            return $this->refundUpdate($chargeId, $amount, $this->api->get('/v2/refunds/'.rawurlencode($cached)));
        }

        $response = $this->api->post('/v2/refunds', [
            'charge_id' => $chargeId,
            'amount' => TapAmount::toValue($amount),
            'currency' => $amount->currency(),
            'reason' => 'requested_by_customer',
            'metadata' => ['idempotency' => mb_substr(hash('sha256', $idempotencyKey), 0, 40)],
        ]);
        $refundId = $response['id'] ?? null;

        if (is_string($refundId) && preg_match(self::REFUND_ID, $refundId) === 1) {
            Cache::put($cacheKey, $refundId, now()->addDays(7));
        }

        return $this->refundUpdate($chargeId, $amount, $response);
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    private function fromCharge(string $reference, array $charge): PaymentUpdate
    {
        $status = strtoupper((string) ($charge['status'] ?? ''));
        $amount = $this->money($charge['amount'] ?? null, $charge['currency'] ?? null);

        $kind = match ($status) {
            'CAPTURED' => PaymentUpdateKind::Succeeded,
            'AUTHORIZED' => PaymentUpdateKind::Processing,
            'ABANDONED', 'CANCELLED', 'TIMEDOUT', 'VOID' => PaymentUpdateKind::Expired,
            'FAILED', 'DECLINED', 'RESTRICTED' => PaymentUpdateKind::Failed,
            default => PaymentUpdateKind::Pending,
        };

        return new PaymentUpdate(
            $kind,
            $reference,
            $kind === PaymentUpdateKind::Succeeded ? $reference : null,
            $kind === PaymentUpdateKind::Succeeded ? $amount : null,
            $kind === PaymentUpdateKind::Failed ? 'Tap declined this payment.' : null,
        );
    }

    private function refundFromEvent(string $refundId): ?RefundUpdate
    {
        if (preg_match(self::REFUND_ID, $refundId) !== 1) {
            return null;
        }

        try {
            $refund = $this->api->get('/v2/refunds/'.rawurlencode($refundId));
        } catch (GatewayException $e) {
            if ($e->retryable) {
                throw $e;
            }

            Log::info('Tap refund webhook could not be read from Tap.');

            return null;
        }

        $chargeId = $refund['charge_id'] ?? null;
        $amount = $this->money($refund['amount'] ?? null, $refund['currency'] ?? null);

        if (! is_string($chargeId) || preg_match(self::CHARGE_ID, $chargeId) !== 1 || $amount === null || ! $amount->isPositive()) {
            Log::info('Tap refund webhook had no usable charge or amount.');

            return null;
        }

        return $this->refundUpdate($chargeId, $amount, $refund);
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function refundUpdate(string $chargeId, Money $amount, array $refund): RefundUpdate
    {
        $refundId = $refund['id'] ?? null;
        $status = strtoupper((string) ($refund['status'] ?? ''));

        if (! is_string($refundId) || preg_match(self::REFUND_ID, $refundId) !== 1) {
            throw new GatewayException('Tap did not confirm the refund. Check the Tap dashboard before trying again.');
        }

        $returned = $this->money($refund['amount'] ?? null, $refund['currency'] ?? null);

        if ($returned !== null && ! $returned->equals($amount)) {
            throw new GatewayException('Tap returned a refund amount that does not match this refund.');
        }

        $state = in_array($status, ['REFUNDED', 'ACCEPTED', 'SUCCESS'], true)
            ? RefundUpdate::SUCCEEDED
            : (in_array($status, ['PENDING', 'IN_PROGRESS', 'INITIATED'], true) ? RefundUpdate::PENDING : RefundUpdate::FAILED);

        return new RefundUpdate(
            $refundId,
            $chargeId,
            $amount,
            $state,
            $state === RefundUpdate::FAILED ? 'Tap declined this refund.' : null,
        );
    }

    private function money(mixed $value, mixed $currency): ?Money
    {
        if (! is_string($currency) || preg_match('/^[A-Za-z]{3}$/', $currency) !== 1) {
            return null;
        }

        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && preg_match('/^\d+(\.\d+)?$/', $value) === 1)) {
            return null;
        }

        try {
            return TapAmount::toMoney(TapAmount::hashValue($value, $currency), $currency);
        } catch (GatewayException|InvalidArgumentException) {
            return null;
        }
    }
}
