<?php

namespace ModrixCommerce\PayTabs;

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
use Illuminate\Support\Facades\DB;
use Throwable;

final class PayTabsGateway implements PaymentGateway
{
    public const CODE = 'paytabs';

    public function __construct(private readonly PayTabsSettings $settings, private readonly PayTabsApi $api) {}

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
        return 'Pay on PayTabs’ secure payment page. Payment details are entered there.';
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
        return $cart->grandTotal()->isPositive() && in_array($cart->grandTotal()->currency(), $this->settings->value('currencies'), true);
    }

    public function createSession(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): PaymentSession
    {
        if (! $this->isConfigured() || ! in_array($payment->currency, $this->settings->value('currencies'), true)) {
            throw new GatewayException('PayTabs is unavailable for this payment.');
        }
        $return = route('modrixcommerce.paytabs.return', $payment);
        if (! str_starts_with($return, 'https://')) {
            throw new GatewayException('PayTabs requires a public HTTPS store address.');
        }
        $context = $this->api->snapshot();
        $context->payment_id = $payment->id;
        $context->cart_id = 'modrix-'.$payment->id.'-'.substr(hash_hmac('sha256', (string) $payment->id, (string) config('app.key')), 0, 20);
        DB::table('paytabs_payment_contexts')->insert((array) $context);
        $body = [
            'tran_type' => 'sale', 'tran_class' => 'ecom', 'cart_id' => $context->cart_id,
            'cart_currency' => $payment->currency, 'cart_amount' => $payment->money()->toDisplayDecimal(),
            'cart_description' => mb_substr('Order '.($order->number ?? $order->id), 0, 128),
            'return' => $return, 'paypage_lang' => $this->settings->value('language'),
            'hide_shipping' => (bool) $this->settings->value('hide_shipping'),
            'payment_methods' => $this->settings->value('all_methods') ? ['all'] : $this->settings->value('payment_methods'),
        ];
        if ($this->settings->value('callbacks_enabled')) {
            $body['callback'] = route('webhooks.payments', self::CODE);
        }
        $order->loadMissing(['billingAddress', 'shippingAddress']);
        foreach (['customer_details' => 'billingAddress', 'shipping_details' => 'shippingAddress'] as $field => $relation) {
            if (! $this->settings->value($field === 'customer_details' ? 'send_billing' : 'send_shipping')) {
                continue;
            }
            $address = $order->$relation;
            $details = ['email' => mb_substr($order->email, 0, 254)];
            if ($address !== null) {
                $details += array_filter([
                    'name' => mb_substr($address->first_name.' '.$address->last_name, 0, 128),
                    'phone' => mb_substr($address->phone ?? $order->phone ?? '', 0, 32),
                    'street1' => mb_substr($address->line1, 0, 128), 'city' => mb_substr($address->city, 0, 64),
                    'state' => mb_substr($address->region ?? '', 0, 64), 'country' => $address->country_code,
                    'zip' => mb_substr($address->postal_code ?? '', 0, 16),
                ], fn ($v) => $v !== '');
            }
            $body[$field] = $details;
        }
        $result = $this->api->post('request', $body, $context);
        $ref = $result['tran_ref'] ?? null;
        $redirect = $result['redirect_url'] ?? null;
        $this->assertProfile($result, $context);
        $host = PayTabsSettings::REGIONS[$context->region][1];
        if (! self::validReference($ref) || ! is_string($redirect)
            || ! str_starts_with($redirect, $host.'/payment/') || preg_match('/[\x00-\x20\\\\]/', $redirect)
            || ($result['cart_id'] ?? null) !== $context->cart_id) {
            throw new GatewayException('PayTabs returned an unexpected payment page or cart reference.');
        }

        return new PaymentSession($ref, $redirect, now()->addMinutes($this->settings->value('session_minutes')));
    }

    public function expireSession(Payment $payment): void
    {
        // No documented HPP revocation API. Never issue a financial void for an unpaid page.
    }

    public function context(Payment $payment): object
    {
        $context = DB::table('paytabs_payment_contexts')->where('payment_id', $payment->id)->first();
        if ($payment->provider !== self::CODE || $context === null) {
            throw new GatewayException('PayTabs payment context is missing.');
        }

        return $context;
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $context = $this->context($payment);
        $result = $this->query((string) $payment->provider_reference, $context);
        if (strtolower((string) ($result['tran_type'] ?? '')) !== 'sale' || ($result['cart_id'] ?? null) !== $context->cart_id) {
            throw new GatewayException('PayTabs returned a different cart or transaction type.');
        }
        $kind = match ($result['payment_result']['response_status'] ?? '') {
            'A' => PaymentUpdateKind::Succeeded,
            'H' => PaymentUpdateKind::Processing,
            'P' => PaymentUpdateKind::Pending,
            'V', 'X' => PaymentUpdateKind::Expired,
            'D', 'E' => PaymentUpdateKind::Failed,
            default => PaymentUpdateKind::Pending,
        };
        $amount = in_array($kind, [PaymentUpdateKind::Succeeded, PaymentUpdateKind::Processing], true) ? $this->money($result) : null;

        return new PaymentUpdate($kind, $payment->provider_reference, $result['tran_ref'], $amount, $kind === PaymentUpdateKind::Failed ? 'PayTabs declined this payment.' : null);
    }

    private function query(string $reference, object $context): array
    {
        if (! self::validReference($reference)) {
            throw new GatewayException('Invalid PayTabs transaction reference.');
        }
        $result = $this->api->post('query', ['tran_ref' => $reference], $context);
        $this->assertProfile($result, $context);
        if (($result['tran_ref'] ?? null) !== $reference) {
            throw new GatewayException('PayTabs returned a different transaction reference.');
        }

        return $result;
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        if (! $this->settings->value('callbacks_enabled') || strlen($payload) > 262144) {
            throw new InvalidWebhook('PayTabs callbacks are disabled or too large.');
        }
        try {
            $data = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new InvalidWebhook('Malformed PayTabs notification.');
        }
        if (! is_array($data)) {
            throw new InvalidWebhook('Malformed PayTabs notification.');
        }
        $profile = $data['profile_id'] ?? $data['profileId'] ?? '';
        $type = $data['tran_type'] ?? '';
        if ((! is_string($profile) && ! is_int($profile)) || ! is_string($type)
            || (isset($data['cart_id']) && (! is_string($data['cart_id']) || strlen($data['cart_id']) > 64))
            || (isset($data['previous_tran_ref']) && $data['previous_tran_ref'] !== '' && ! self::validReference($data['previous_tran_ref']))) {
            throw new InvalidWebhook('Invalid PayTabs notification fields.');
        }
        $profile = (string) $profile;
        $valid = false;
        foreach ([true, false] as $test) {
            $key = $this->settings->serverKey($test);
            if ($profile !== '' && $profile === $this->settings->profileId($test) && $key !== null
                && PayTabsSignature::matches($payload, $headers['signature'] ?? null, $key)) {
                $valid = true;
            }
        }
        if (! $valid || ! self::validReference($data['tran_ref'] ?? null)) {
            throw new InvalidWebhook('PayTabs signature or profile does not match.');
        }

        return [
            'id' => hash('sha256', $payload), 'type' => strtolower($type),
            'tran_ref' => $data['tran_ref'], 'profile_id' => $profile,
            'cart_id' => is_string($data['cart_id'] ?? null) ? $data['cart_id'] : '',
            'previous_tran_ref' => is_string($data['previous_tran_ref'] ?? null) ? $data['previous_tran_ref'] : '',
        ];
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        if (($event['type'] ?? '') === 'sale') {
            $context = DB::table('paytabs_payment_contexts')->where('cart_id', $event['cart_id'])->first();
            $payment = $context === null ? null : Payment::query()->where('provider', self::CODE)->find($context->payment_id);
            if ($payment !== null && $payment->provider_reference === null) {
                throw new GatewayException('PayTabs callback arrived before the payment reference was saved.', retryable: true);
            }
            if ($payment === null || $payment->provider_reference !== $event['tran_ref'] || (string) $context->profile_id !== $event['profile_id']) {
                return new GatewayEvent($event['id'], $event['type']);
            }

            return new GatewayEvent($event['id'], $event['type'], payment: $this->retrieve($payment));
        }
        if (($event['type'] ?? '') === 'refund') {
            $payment = Payment::query()->where('provider', self::CODE)->where('provider_payment_id', $event['previous_tran_ref'])->first();
            if ($payment !== null) {
                $context = $this->context($payment);
                if ((string) $context->profile_id !== $event['profile_id']) {
                    throw new GatewayException('PayTabs refund profile does not match.');
                }
                $refund = $this->refundUpdate($this->query($event['tran_ref'], $context), $payment);
                DB::table('paytabs_refund_requests')->where('result_reference', $refund->reference)->update(['result_status' => $refund->status, 'updated_at' => now()]);

                return new GatewayEvent($event['id'], $event['type'], refund: $refund);
            }
        }

        return new GatewayEvent($event['id'], $event['type']);
    }

    public function verifyReturn(Payment $payment, array $fields): bool
    {
        $context = $this->context($payment);
        $key = $this->settings->serverKey((bool) $context->test_mode);

        return $key !== null && hash_equals($context->key_hash, hash('sha256', $key))
            && ($fields['tranRef'] ?? null) === $payment->provider_reference
            && ($fields['cartId'] ?? null) === $context->cart_id && PayTabsSignature::returnMatches($fields, $key);
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        if (! $this->settings->value('refunds_enabled')) {
            throw new GatewayException('PayTabs API refunds are disabled in payment settings.');
        }
        if ($payment->provider !== self::CODE || $payment->status !== Payment::STATUS_PAID || ! self::validReference($payment->provider_payment_id)
            || ! $amount->isPositive() || $amount->currency() !== $payment->currency || $amount->greaterThan($payment->money()) || $idempotencyKey === '') {
            throw new GatewayException('Invalid PayTabs refund payment or amount.');
        }

        return Cache::lock('paytabs:refund:'.$payment->id, 60)->block(5, function () use ($payment, $amount, $idempotencyKey) {
            $hash = hash('sha256', $idempotencyKey);
            $previous = DB::table('paytabs_refund_requests')->where('key_hash', $hash)->first();
            if ($previous !== null) {
                if ((int) $previous->payment_id !== $payment->id || $previous->amount !== $amount->toDecimal() || $previous->currency !== $amount->currency()) {
                    throw new GatewayException('The PayTabs refund request key was already used for a different payment or amount.');
                }
                if ($previous->status === 'complete') {
                    if ($previous->result_status === RefundUpdate::FAILED) {
                        throw new GatewayException('PayTabs rejected this refund. Reconcile it before creating another.');
                    }

                    return new RefundUpdate($previous->result_reference, $payment->provider_payment_id, $amount, $previous->result_status);
                }
                throw new GatewayException('This PayTabs refund was already attempted. Reconcile it in the merchant dashboard before retrying.');
            }
            if (DB::table('paytabs_refund_requests')->where('payment_id', $payment->id)->where('status', 'sending')->exists()) {
                throw new GatewayException('A previous PayTabs refund has an unknown outcome. Reconcile it before creating another.');
            }
            $context = $this->context($payment);
            // Persist before sending. Keep the reservation after a timeout, crash or unreadable response.
            DB::table('paytabs_refund_requests')->insert([
                'key_hash' => $hash, 'payment_id' => $payment->id, 'amount' => $amount->toDecimal(), 'currency' => $amount->currency(),
                'status' => 'sending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $body = [
                'tran_type' => 'refund', 'tran_class' => 'ecom', 'tran_ref' => $payment->provider_payment_id,
                'cart_id' => $context->cart_id, 'cart_currency' => $amount->currency(), 'cart_amount' => $amount->toDisplayDecimal(),
                'cart_description' => 'Refund '.$payment->order->number.' '.substr($hash, 0, 16),
            ];
            if ($this->settings->value('callbacks_enabled')) {
                $body['callback'] = route('webhooks.payments', self::CODE);
            }
            $result = $this->api->post('request', $body, $context);
            $this->assertProfile($result, $context);
            $refund = $this->refundUpdate($result, $payment);
            if (! $refund->amount->equals($amount)) {
                throw new GatewayException('PayTabs returned a different refund amount. Check the dashboard.');
            }
            DB::table('paytabs_refund_requests')->where('key_hash', $hash)->update([
                'status' => $refund->status === RefundUpdate::FAILED ? 'failed' : 'complete',
                'result_reference' => $refund->reference, 'result_status' => $refund->status, 'updated_at' => now(),
            ]);
            if ($refund->status === RefundUpdate::FAILED) {
                throw new GatewayException('PayTabs rejected the refund. Check the merchant dashboard.');
            }

            return $refund;
        });
    }

    private function refundUpdate(array $result, Payment $payment): RefundUpdate
    {
        if (! self::validReference($result['tran_ref'] ?? null) || ($result['previous_tran_ref'] ?? null) !== $payment->provider_payment_id
            || strtolower((string) ($result['tran_type'] ?? '')) !== 'refund') {
            throw new GatewayException('PayTabs returned an unexpected refund reference or type.');
        }
        $amount = $this->money($result);
        if (! $amount->isPositive() || $amount->currency() !== $payment->currency || $amount->greaterThan($payment->money())) {
            throw new GatewayException('PayTabs returned an invalid refund amount.');
        }
        $status = match ($result['payment_result']['response_status'] ?? '') {
            'A' => RefundUpdate::SUCCEEDED, 'P', 'H' => RefundUpdate::PENDING,
            'E', 'D', 'V', 'X' => RefundUpdate::FAILED,
            default => throw new GatewayException('PayTabs refund status is unknown. Check the dashboard before retrying.'),
        };

        return new RefundUpdate($result['tran_ref'], $payment->provider_payment_id, $amount, $status, $status === RefundUpdate::FAILED ? 'PayTabs rejected this refund.' : null);
    }

    public function reconcileRefund(Payment $payment, string $reference): RefundUpdate
    {
        return Cache::lock('paytabs:refund:'.$payment->id, 60)->block(5, function () use ($payment, $reference) {
            $refund = $this->refundUpdate($this->query($reference, $this->context($payment)), $payment);
            DB::table('paytabs_refund_requests')->where('payment_id', $payment->id)->where('amount', $refund->amount->toDecimal())
                ->where(function ($query) use ($reference) {
                    $query->where('result_reference', $reference)->orWhere('status', 'sending');
                })->update(['status' => $refund->status === RefundUpdate::FAILED ? 'failed' : 'complete', 'result_reference' => $reference, 'result_status' => $refund->status, 'updated_at' => now()]);

            return $refund;
        });
    }

    private function assertProfile(array $data, object $context): void
    {
        if ((string) ($data['profile_id'] ?? $data['profileId'] ?? '') !== (string) $context->profile_id) {
            throw new GatewayException('PayTabs returned a different merchant profile.');
        }
    }

    private function money(array $result): Money
    {
        $amount = $result['cart_amount'] ?? null;
        $currency = $result['cart_currency'] ?? null;
        if (! is_scalar($amount) || ! preg_match('/^\d{1,13}(\.\d{1,4})?$/D', (string) $amount)
            || ! is_string($currency) || ! in_array($currency, PayTabsSettings::CURRENCIES, true)) {
            throw new GatewayException('PayTabs returned an invalid payment amount or currency.');
        }

        return Money::of((string) $amount, $currency);
    }

    public static function validReference(mixed $reference): bool
    {
        return is_string($reference) && preg_match('/^[A-Za-z0-9]{8,64}$/D', $reference);
    }
}
