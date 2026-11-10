<?php

namespace ModrixCommerce\CCAvenue;

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
use Illuminate\Support\Str;

final class CCAvenueGateway implements PaymentGateway
{
    public const CODE = 'ccavenue';

    public function __construct(private readonly CCAvenueSettings $settings, private readonly CCAvenueApi $api) {}

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
        return $this->settings->value('label');
    }

    public function instructions(): string
    {
        return 'Pay securely on CCAvenue. Card details are entered on the payment provider’s page.';
    }

    public function isConfigured(): bool
    {
        return $this->settings->ready();
    }

    public function allowsFulfillmentBeforePayment(): bool
    {
        return false;
    }

    public function isAvailable(PricedCart $cart): bool
    {
        return $cart->grandTotal()->isPositive() && in_array($cart->grandTotal()->currency(), $this->settings->value('currencies'), true);
    }

    public function createSession(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): PaymentSession
    {
        if (! $this->settings->ready() || ! in_array($payment->currency, $this->settings->value('currencies'), true)) {
            throw new GatewayException('CCAvenue is unavailable for this payment.');
        }
        // Stay below CCAvenue's 30-character limit even with large local payment IDs.
        $ref = ($this->settings->value('test_mode') ? 'T' : 'L').Str::lower(Str::random(24));
        $callback = route('modrixcommerce.ccavenue.response');
        $expires = now()->addMinutes($this->settings->value('session_minutes'));
        $fields = [
            'merchant_id' => $this->settings->value('merchant_id'),
            'order_id' => $ref,
            // Stable across handoff reloads. CCAvenue uses tid to detect repeat initiation.
            'tid' => str_pad((string) hexdec(substr(hash('sha256', $ref), 0, 13)), 17, '0', STR_PAD_LEFT),
            'currency' => $payment->currency,
            'amount' => $payment->money()->toDisplayDecimal(),
            'redirect_url' => $callback,
            'cancel_url' => $callback,
            'language' => $this->settings->value('language'),
        ];
        $order->loadMissing(['billingAddress', 'shippingAddress']);
        foreach (['billing' => 'billingAddress', 'delivery' => 'shippingAddress'] as $prefix => $relation) {
            if (! $this->settings->value($prefix === 'billing' ? 'send_billing' : 'send_shipping')) {
                continue;
            }
            $address = $order->$relation;
            $fields[$prefix.'_email'] = mb_substr($order->email, 0, 70);
            if ($address !== null) {
                $fields += [
                    $prefix.'_name' => mb_substr($address->first_name.' '.$address->last_name, 0, 60),
                    $prefix.'_address' => mb_substr($address->line1.' '.($address->line2 ?? ''), 0, 150),
                    $prefix.'_city' => mb_substr($address->city, 0, 30),
                    $prefix.'_state' => mb_substr($address->region ?? '', 0, 30),
                    $prefix.'_zip' => mb_substr($address->postal_code ?? '', 0, 15),
                    $prefix.'_country' => $address->country_code,
                    $prefix.'_tel' => mb_substr($address->phone ?? $order->phone ?? '', 0, 20),
                ];
            }
        }
        Cache::put('ccavenue:handoff:'.$payment->id, [
            'action' => $this->settings->checkoutUrl(),
            'access_code' => $this->settings->secret('access_code'),
            'encRequest' => CCAvenueCrypto::encrypt(http_build_query($fields, '', '&', PHP_QUERY_RFC3986), $this->settings->secret('working_key')),
        ], $expires);

        return new PaymentSession($ref, route('modrixcommerce.ccavenue.handoff', $payment), $expires);
    }

    public function expireSession(Payment $payment): void
    {
        // Hosted CCAvenue requests cannot be remotely revoked. Stop local handoff access.
        Cache::forget('ccavenue:handoff:'.$payment->id);
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $ref = (string) $payment->provider_reference;
        if ($ref === '' || ! str_starts_with($ref, $this->settings->value('test_mode') ? 'T' : 'L')) {
            throw new GatewayException('CCAvenue environment differs from this payment. Restore its original environment to reconcile.');
        }
        $result = $this->api->command('orderStatusTracker', ['order_no' => $ref, 'reference_no' => $payment->provider_payment_id ?? '']);
        if ((string) ($result['status'] ?? '') !== '0') {
            throw new GatewayException('CCAvenue has not returned a valid order status yet.', retryable: true);
        }
        if (($result['order_no'] ?? null) !== $ref) {
            throw new GatewayException('CCAvenue returned a different order reference.');
        }
        $kind = match (strtolower((string) ($result['order_status'] ?? ''))) {
            'shipped', 'successful', 'success' => PaymentUpdateKind::Succeeded,
            'initiated', 'awaited', 'in progress' => PaymentUpdateKind::Pending,
            'aborted', 'cancelled', 'canceled', 'auto-cancelled' => PaymentUpdateKind::Expired,
            'unsuccessful', 'failure', 'failed', 'invalid' => PaymentUpdateKind::Failed,
            default => PaymentUpdateKind::Pending,
        };
        $amount = null;
        $tracking = isset($result['reference_no']) ? (string) $result['reference_no'] : null;
        if ($kind === PaymentUpdateKind::Succeeded) {
            $decimal = (string) ($result['order_amt'] ?? $result['order_amount'] ?? '');
            $currency = (string) ($result['order_currncy'] ?? $result['order_currency'] ?? '');
            if (! preg_match('/^\d{1,10}(\.\d{1,4})?$/D', $decimal) || ! preg_match('/^[A-Z]{3}$/D', $currency) || ! ctype_digit($tracking ?? '')) {
                throw new GatewayException('CCAvenue payment confirmation is incomplete.');
            }
            $amount = Money::of($decimal, $currency);
        }

        return new PaymentUpdate($kind, $ref, $tracking, $amount, $kind === PaymentUpdateKind::Failed ? 'CCAvenue declined this payment.' : null);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        if (! $this->settings->value('callbacks_enabled') || strlen($payload) > 262144) {
            throw new InvalidWebhook('CCAvenue callbacks are disabled or payload is too large.');
        }
        parse_str($payload, $outer);
        $key = $this->settings->secret('working_key');
        if ($key === null || ! is_string($outer['encResp'] ?? null)) {
            throw new InvalidWebhook('Missing CCAvenue encrypted response.');
        }
        try {
            parse_str(CCAvenueCrypto::decrypt($outer['encResp'], $key), $fields);
        } catch (GatewayException) {
            throw new InvalidWebhook('Invalid CCAvenue encrypted response.');
        }
        $ref = $fields['order_id'] ?? null;
        if (! is_string($ref) || ! preg_match('/^[TL][a-z0-9]{24}$/D', $ref)) {
            throw new InvalidWebhook('CCAvenue order reference is invalid.');
        }

        // AES-CBC is not an authentication signature. Store only a hint, and always
        // retrieve server-side before changing financial state. Never persist PII.
        return ['id' => hash('sha256', $outer['encResp']), 'type' => 'order.notification', 'order_ref' => $ref];
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $payment = Payment::query()->where('provider', self::CODE)->where('provider_reference', $event['order_ref'] ?? '')->first();

        return new GatewayEvent($event['id'], $event['type'], payment: $payment === null ? null : $this->retrieve($payment));
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        if (! $this->settings->value('refunds_enabled')) {
            throw new GatewayException('CCAvenue API refunds are disabled in payment settings.');
        }
        if ($payment->provider !== self::CODE || $payment->status !== Payment::STATUS_PAID || ! ctype_digit((string) $payment->provider_payment_id)
            || ! $amount->isPositive() || $amount->currency() !== $payment->currency || $amount->greaterThan($payment->money())) {
            throw new GatewayException('This CCAvenue payment or refund amount is invalid.');
        }
        if (! str_starts_with((string) $payment->provider_reference, $this->settings->value('test_mode') ? 'T' : 'L')) {
            throw new GatewayException('Restore the payment’s original CCAvenue environment before refunding.');
        }
        $ref = substr(hash('sha256', $idempotencyKey), 0, 30);
        $result = $this->api->command('refundOrder', [
            'reference_no' => $payment->provider_payment_id,
            'refund_amount' => $amount->toDisplayDecimal(),
            'refund_ref_no' => $ref,
        ]);
        if ((string) ($result['refund_status'] ?? '') !== '0') {
            throw new GatewayException('CCAvenue did not accept the refund. Check its dashboard before retrying.');
        }

        // Acceptance starts asynchronous bank processing; it is not settlement.
        return new RefundUpdate($ref, $payment->provider_payment_id, $amount, RefundUpdate::PENDING);
    }
}
