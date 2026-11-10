<?php

namespace ModrixCommerce\Razorpay;

use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Orders\Enums\OrderStatus;
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

final class RazorpayGateway implements PaymentGateway
{
    public const CODE = 'razorpay';

    public function __construct(private readonly RazorpaySettings $settings, private readonly RazorpayApi $api) {}

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
        return 'Pay securely using Razorpay. Your order is confirmed after the payment is captured.';
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
            throw new GatewayException('Razorpay is unavailable for this payment.');
        }
        if (! str_starts_with(route('modrixcommerce.razorpay.return', $payment), 'https://')) {
            throw new GatewayException('Razorpay requires the store to use a public HTTPS address.');
        }
        $context = $this->api->snapshot();
        $context->payment_id = $payment->id;
        $context->receipt = 'modrix-p'.$payment->id.'-'.substr(hash_hmac('sha256', (string) $payment->id, (string) config('app.key')), 0, 10);
        DB::table('razorpay_payment_contexts')->insert((array) $context);
        $body = ['amount' => RazorpayAmount::toMinor($payment->money()), 'currency' => $payment->currency, 'receipt' => $context->receipt, 'partial_payment' => false];
        $result = $this->api->request('post', 'orders', $body, $context);
        if (! self::validId($result['id'] ?? null, 'order') || ($result['entity'] ?? null) !== 'order'
            || ($result['receipt'] ?? null) !== $context->receipt || ($result['amount'] ?? null) !== $body['amount'] || ($result['currency'] ?? null) !== $payment->currency) {
            throw new GatewayException('Razorpay returned an unexpected order, receipt or amount.');
        }
        DB::table('razorpay_payment_contexts')->where('payment_id', $payment->id)->update(['order_id' => $result['id']]);

        return new PaymentSession($result['id'], route('modrixcommerce.razorpay.checkout', $payment), now()->addMinutes($this->settings->value('session_minutes')));
    }

    public function expireSession(Payment $payment): void
    {
        // Razorpay Orders do not expose a cancellation API. The local handoff expires.
    }

    public function context(Payment $payment): object
    {
        $context = DB::table('razorpay_payment_contexts')->where('payment_id', $payment->id)->first();
        if ($payment->provider !== self::CODE || $context === null || ! self::validId($context->order_id, 'order')) {
            throw new GatewayException('Razorpay payment context is missing.');
        }

        return $context;
    }

    public function checkoutOptions(Payment $payment): array
    {
        $context = $this->context($payment);
        [$key] = $this->api->credentials($context);
        $display = ['hide' => [['method' => 'cod']]];
        if (! $this->settings->value('all_methods')) {
            $display += [
                'blocks' => ['modrix' => ['name' => 'Payment methods', 'instruments' => array_map(fn ($method) => ['method' => $method], $this->settings->value('payment_methods'))]],
                'sequence' => ['block.modrix'], 'preferences' => ['show_default_blocks' => false],
            ];
        }
        $options = [
            'key' => $key, 'order_id' => $context->order_id, 'amount' => RazorpayAmount::toMinor($payment->money()), 'currency' => $payment->currency,
            'name' => $this->settings->value('brand_name'), 'description' => 'Order '.$payment->order->number,
            'callback_url' => route('modrixcommerce.razorpay.return', $payment), 'redirect' => false,
            'timeout' => max(1, $payment->expires_at?->getTimestamp() - now()->getTimestamp()),
            'theme' => ['color' => $this->settings->value('theme_color')],
            'retry' => ['enabled' => (bool) $this->settings->value('retry_enabled')],
            'modal' => ['confirm_close' => (bool) $this->settings->value('confirm_close')],
            'config' => ['display' => $display],
        ];
        if ($this->settings->value('prefill_customer')) {
            $order = $payment->order;
            $order->loadMissing('billingAddress');
            $options['prefill'] = array_filter([
                'email' => $order->email, 'contact' => $order->phone,
                'name' => $order->billingAddress === null ? null : $order->billingAddress->first_name.' '.$order->billingAddress->last_name,
            ], fn ($v) => is_string($v) && $v !== '');
        }

        return $options;
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $context = $this->context($payment);
        $order = $this->api->request('get', 'orders/'.$context->order_id, context: $context);
        if (($order['id'] ?? null) !== $payment->provider_reference || ($order['receipt'] ?? null) !== $context->receipt
            || ($order['currency'] ?? null) !== $payment->currency || ($order['amount'] ?? null) !== RazorpayAmount::toMinor($payment->money())) {
            throw new GatewayException('Razorpay returned a different order, receipt or amount.');
        }
        $collection = $this->api->request('get', 'orders/'.$context->order_id.'/payments', context: $context);
        if (! is_array($collection['items'] ?? null)) {
            throw new GatewayException('Razorpay returned an unreadable payment collection.', retryable: true);
        }
        $authorized = null;
        foreach ($collection['items'] as $item) {
            if (! is_array($item) || ($item['order_id'] ?? null) !== $context->order_id || ! self::validId($item['id'] ?? null, 'pay')) {
                throw new GatewayException('Razorpay returned a payment for another order.');
            }
            if (in_array($item['status'] ?? '', ['captured', 'refunded'], true) && ($item['captured'] ?? false) === true) {
                return $this->paymentUpdate($payment, $item, PaymentUpdateKind::Succeeded);
            }
            if (($item['status'] ?? '') === 'authorized') {
                $authorized = $item;
            }
        }
        if ($authorized !== null) {
            $amount = RazorpayAmount::money($authorized['amount'] ?? null, $authorized['currency'] ?? null);
            if ($this->settings->value('capture_authorized') && $amount->equals($payment->money()) && $payment->isOpen()
                && ! $payment->expires_at?->isPast() && $payment->order->payment_status->isOutstanding() && $payment->order->status !== OrderStatus::Cancelled) {
                $capture = $this->api->request('post', 'payments/'.$authorized['id'].'/capture', ['amount' => RazorpayAmount::toMinor($payment->money()), 'currency' => $payment->currency], $context);
                if (($capture['id'] ?? null) !== $authorized['id'] || ($capture['order_id'] ?? null) !== $context->order_id
                    || ($capture['status'] ?? null) !== 'captured' || ($capture['captured'] ?? false) !== true) {
                    throw new GatewayException('Razorpay capture has not been confirmed. Reconcile the payment before retrying.', retryable: true);
                }

                return $this->paymentUpdate($payment, $capture, PaymentUpdateKind::Succeeded);
            }

            return $this->paymentUpdate($payment, $authorized, PaymentUpdateKind::Processing);
        }

        // A failed attempt does not fail a retryable Razorpay Order.
        return new PaymentUpdate($payment->expires_at?->isPast() ? PaymentUpdateKind::Expired : PaymentUpdateKind::Pending, $payment->provider_reference);
    }

    private function paymentUpdate(Payment $payment, array $entity, PaymentUpdateKind $kind): PaymentUpdate
    {
        return new PaymentUpdate($kind, $payment->provider_reference, $entity['id'], RazorpayAmount::money($entity['amount'] ?? null, $entity['currency'] ?? null));
    }

    public function verifyReturn(Payment $payment, array $fields): bool
    {
        $context = $this->context($payment);
        [, $secret] = $this->api->credentials($context);

        return self::validId($fields['razorpay_payment_id'] ?? null, 'pay')
            && ($fields['razorpay_order_id'] ?? null) === $context->order_id
            && self::signatureMatches($context->order_id.'|'.$fields['razorpay_payment_id'], $fields['razorpay_signature'] ?? null, $secret);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        if (! $this->settings->value('webhooks_enabled') || strlen($payload) > 262144) {
            throw new InvalidWebhook('Razorpay webhooks are disabled or too large.');
        }
        $modes = [];
        foreach ([true, false] as $test) {
            $secret = $this->settings->secret('webhook_secret', $test);
            if ($secret !== null && self::signatureMatches($payload, $headers['x-razorpay-signature'] ?? null, $secret)) {
                $modes[] = $test;
            }
        }
        if ($modes === []) {
            throw new InvalidWebhook('Razorpay webhook signature does not match.');
        }
        try {
            $data = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new InvalidWebhook('Malformed Razorpay webhook.');
        }
        if (! is_array($data) || ! is_string($data['event'] ?? null) || ! is_array($data['payload'] ?? null)) {
            throw new InvalidWebhook('Malformed Razorpay webhook.');
        }
        $order = $data['payload']['payment']['entity']['order_id'] ?? $data['payload']['order']['entity']['id'] ?? null;
        $pay = $data['payload']['refund']['entity']['payment_id'] ?? $data['payload']['payment']['entity']['id'] ?? null;
        $refund = $data['payload']['refund']['entity']['id'] ?? null;

        return [
            'id' => hash('sha256', $payload), 'type' => mb_substr($data['event'], 0, 120), 'modes' => $modes,
            'order_id' => self::validId($order, 'order') ? $order : null,
            'payment_id' => self::validId($pay, 'pay') ? $pay : null,
            'refund_id' => self::validId($refund, 'rfnd') ? $refund : null,
        ];
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        if (in_array($event['type'], ['order.paid', 'payment.authorized', 'payment.captured', 'payment.failed'], true) && $event['order_id'] !== null) {
            $context = DB::table('razorpay_payment_contexts')->where('order_id', $event['order_id'])->first();
            $payment = $context === null ? null : Payment::query()->where('provider', self::CODE)->find($context->payment_id);
            if ($payment !== null && in_array((bool) $context->test_mode, $event['modes'], true)) {
                if ($payment->provider_reference === null) {
                    throw new GatewayException('Razorpay webhook arrived before the order reference was saved.', retryable: true);
                }

                return new GatewayEvent($event['id'], $event['type'], payment: $this->retrieve($payment));
            }
        }
        if (in_array($event['type'], ['refund.created', 'refund.processed', 'refund.failed'], true) && $event['payment_id'] !== null && $event['refund_id'] !== null) {
            $payment = Payment::query()->where('provider', self::CODE)->where('provider_payment_id', $event['payment_id'])->first();
            if ($payment === null) {
                // The capture notification may still be queued; preserve this refund for a retry.
                throw new GatewayException('The original Razorpay capture has not been reconciled yet.', retryable: true);
            }
            $context = $this->context($payment);
            if (! in_array((bool) $context->test_mode, $event['modes'], true)) {
                throw new GatewayException('Razorpay webhook environment does not match the payment.');
            }

            return new GatewayEvent($event['id'], $event['type'], refund: $this->reconcileRefund($payment, $event['refund_id'], false));
        }

        return new GatewayEvent($event['id'], $event['type']);
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        if (! $this->settings->value('refunds_enabled')) {
            throw new GatewayException('Razorpay API refunds are disabled in payment settings.');
        }
        if ($payment->provider !== self::CODE || $payment->status !== Payment::STATUS_PAID || ! self::validId($payment->provider_payment_id, 'pay')
            || ! $amount->isPositive() || $amount->currency() !== $payment->currency || $amount->greaterThan($payment->money()) || $idempotencyKey === '') {
            throw new GatewayException('Invalid Razorpay refund payment or amount.');
        }

        return Cache::lock('razorpay:refund:'.$payment->id, 60)->block(5, function () use ($payment, $amount, $idempotencyKey) {
            $hash = hash('sha256', $idempotencyKey);
            $previous = DB::table('razorpay_refund_requests')->where('key_hash', $hash)->first();
            if ($previous !== null) {
                if ((int) $previous->payment_id !== $payment->id || $previous->amount !== $amount->toDecimal() || $previous->currency !== $amount->currency()) {
                    throw new GatewayException('This Razorpay refund request key was already used for another payment or amount.');
                }
                if ($previous->status === 'complete' && $previous->result_status !== RefundUpdate::FAILED) {
                    return new RefundUpdate($previous->result_reference, $payment->provider_payment_id, $amount, $previous->result_status);
                }
                throw new GatewayException('This Razorpay refund was already attempted. Reconcile it before retrying.');
            }
            if (DB::table('razorpay_refund_requests')->where('payment_id', $payment->id)->where('status', 'sending')->exists()) {
                throw new GatewayException('A previous Razorpay refund has an unknown outcome. Reconcile it before creating another.');
            }
            $context = $this->context($payment);
            $minor = RazorpayAmount::toMinor($amount);
            DB::table('razorpay_refund_requests')->insert([
                'key_hash' => $hash, 'payment_id' => $payment->id, 'amount' => $amount->toDecimal(), 'currency' => $amount->currency(),
                'status' => 'sending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $result = $this->api->request('post', 'payments/'.$payment->provider_payment_id.'/refund', ['amount' => $minor, 'speed' => 'normal', 'receipt' => substr($hash, 0, 32)], $context, ['X-Refund-Idempotency' => substr($hash, 0, 32)]);
            $refund = $this->refundUpdate($result, $payment);
            if (! $refund->amount->equals($amount)) {
                throw new GatewayException('Razorpay returned a different refund amount. Check the dashboard.');
            }
            $this->rememberRefund($payment, $refund, $hash);
            if ($refund->status === RefundUpdate::FAILED) {
                throw new GatewayException('Razorpay rejected the refund. Check the merchant dashboard.');
            }

            return $refund;
        });
    }

    public function reconcileRefund(Payment $payment, string $reference, bool $resolveUncertain = true): RefundUpdate
    {
        if (! self::validId($reference, 'rfnd')) {
            throw new GatewayException('Invalid Razorpay refund reference.');
        }

        return Cache::lock('razorpay:refund:'.$payment->id, 60)->block(5, function () use ($payment, $reference, $resolveUncertain) {
            $result = $this->api->request('get', 'refunds/'.$reference, context: $this->context($payment));
            if (($result['id'] ?? null) !== $reference) {
                throw new GatewayException('Razorpay returned a different refund reference.');
            }
            $refund = $this->refundUpdate($result, $payment);
            $pending = DB::table('razorpay_refund_requests')->where('payment_id', $payment->id)->where('status', 'sending')->first();
            $requestHash = $pending !== null && ($resolveUncertain || ($result['receipt'] ?? null) === substr($pending->key_hash, 0, 32)) ? $pending->key_hash : null;
            $this->rememberRefund($payment, $refund, $requestHash);

            return $refund;
        });
    }

    private function rememberRefund(Payment $payment, RefundUpdate $refund, ?string $requestHash = null): void
    {
        DB::table('razorpay_refund_requests')->where('payment_id', $payment->id)->where('amount', $refund->amount->toDecimal())
            ->where(function ($query) use ($refund, $requestHash) {
                $query->where('result_reference', $refund->reference);
                if ($requestHash !== null) {
                    $query->orWhere('key_hash', $requestHash);
                }
            })
            ->update(['status' => 'complete', 'result_reference' => $refund->reference, 'result_status' => $refund->status, 'updated_at' => now()]);
    }

    private function refundUpdate(array $result, Payment $payment): RefundUpdate
    {
        if (! self::validId($result['id'] ?? null, 'rfnd') || ($result['entity'] ?? null) !== 'refund' || ($result['payment_id'] ?? null) !== $payment->provider_payment_id) {
            throw new GatewayException('Razorpay returned a refund for another payment.');
        }
        $amount = RazorpayAmount::money($result['amount'] ?? null, $result['currency'] ?? null);
        if (! $amount->isPositive() || $amount->currency() !== $payment->currency || $amount->greaterThan($payment->money())) {
            throw new GatewayException('Razorpay returned an invalid refund amount.');
        }
        $status = match ($result['status'] ?? '') {
            'processed' => RefundUpdate::SUCCEEDED, 'pending' => RefundUpdate::PENDING, 'failed' => RefundUpdate::FAILED,
            default => throw new GatewayException('Razorpay refund status is unknown. Reconcile it before retrying.'),
        };

        return new RefundUpdate($result['id'], $payment->provider_payment_id, $amount, $status, $status === RefundUpdate::FAILED ? 'Razorpay rejected this refund.' : null);
    }

    public static function validId(mixed $value, string $prefix): bool
    {
        return is_string($value) && strlen($value) <= 64 && preg_match('/^'.preg_quote($prefix, '/').'_[A-Za-z0-9]+$/D', $value);
    }

    public static function signatureMatches(string $payload, mixed $signature, string $secret): bool
    {
        return is_string($signature) && preg_match('/^[a-f0-9]{64}$/Di', $signature) && hash_equals(hash_hmac('sha256', $payload, $secret), strtolower($signature));
    }
}
