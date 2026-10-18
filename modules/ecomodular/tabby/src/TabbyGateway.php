<?php

namespace EcoModular\Tabby;

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
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tabby "Pay in 4" on Tabby's hosted payment page.
 *
 * Tabby authorises first and expects the merchant to capture. The platform
 * has no separate capture step, so verification captures: when Tabby reports
 * an AUTHORIZED payment that matches one of our open payments in amount and
 * currency, it is captured in full and reported as paid. Captures and
 * refunds carry a reference_id derived from our own ids, so a retry from the
 * return page, a webhook or the reconcile job can never capture twice.
 */
final class TabbyGateway implements PaymentGateway
{
    public const CODE = 'tabby';

    /** Header carrying the shared secret Tabby echoes on every webhook. */
    public const WEBHOOK_HEADER = 'X-Tabby-Webhook-Secret';

    /** Tabby closes an unpaid checkout session after 20 minutes. */
    public const SESSION_MINUTES = 20;

    public function __construct(
        private readonly TabbySettings $settings,
        private readonly TabbyApi $api,
        private readonly TabbyPayload $payload,
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
        return TabbyCopy::get('method');
    }

    public function instructions(): string
    {
        return TabbyCopy::get('description');
    }

    public function isConfigured(): bool
    {
        return $this->settings->ready();
    }

    public function isAvailable(PricedCart $cart): bool
    {
        $total = $cart->grandTotal();

        return $this->settings->market()?->currency === $total->currency() && $this->settings->allowsAmount($total);
    }

    public function allowsFulfillmentBeforePayment(): bool
    {
        return false;
    }

    public function createSession(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): PaymentSession
    {
        $response = $this->api->request('post', '/api/v2/checkout', $this->payload->session($order, $payment, $returnUrl, $cancelUrl));
        $paymentId = $response['payment']['id'] ?? null;
        $url = $response['configuration']['available_products']['installments'][0]['web_url'] ?? null;

        if (($response['status'] ?? null) !== 'created' || ! is_string($url) || ! is_string($paymentId)) {
            $reason = $response['configuration']['products']['installments']['rejection_reason'] ?? null;

            throw new GatewayException(
                'Tabby did not approve this order'.(is_string($reason) ? " ({$reason})." : '.'),
                customerMessage: TabbyCopy::rejection(is_string($reason) ? $reason : null),
            );
        }

        if (! str_starts_with($url, $this->settings->market()?->checkoutHost().'/') && ! str_starts_with($url, 'https://checkout.tabby.ai/')) {
            throw new GatewayException('Tabby returned a payment page on an unexpected host.');
        }

        return new PaymentSession($paymentId, $url, now()->addMinutes(self::SESSION_MINUTES));
    }

    /**
     * Releases the customer's authorisation when an unpaid order is abandoned
     * or cancelled. Unfinished sessions simply expire at Tabby.
     */
    public function expireSession(Payment $payment): void
    {
        if ($payment->provider_reference === null) {
            return;
        }

        try {
            $tabby = $this->fetch($payment->provider_reference);

            if ($this->status($tabby) === 'AUTHORIZED' && $this->captured($tabby)->isZero()) {
                $this->close($payment->provider_reference);
            }
        } catch (GatewayException $e) {
            Log::info('Tabby payment could not be closed.', ['payment_id' => $payment->id, 'reason' => $e->getMessage()]);
        }
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        return $this->update($this->fetch((string) $payment->provider_reference), $payment);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $secret = $this->settings->webhookSecret();

        if ($secret === null) {
            throw new InvalidWebhook('Tabby webhooks are not registered.');
        }

        $given = $headers[strtolower(self::WEBHOOK_HEADER)] ?? '';

        if ($given === '' || ! hash_equals($secret, $given)) {
            throw new InvalidWebhook('Tabby webhook secret does not match.');
        }

        $data = json_decode($payload, true);

        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! is_string($data['status'] ?? null)) {
            throw new InvalidWebhook('Tabby webhook payload is malformed.');
        }

        if (array_key_exists('is_test', $data) && (bool) $data['is_test'] !== ($this->settings->mode() === 'test')) {
            throw new InvalidWebhook('Tabby webhook environment does not match the configured key.');
        }

        $status = strtolower($data['status']);
        $captures = is_array($data['captures'] ?? null) ? count($data['captures']) : 0;
        $refunds = is_array($data['refunds'] ?? null) ? count($data['refunds']) : 0;

        // Tabby sends no event id: one event per payment state, capture and refund count.
        return [
            'id' => mb_substr("{$data['id']}:{$status}:{$captures}:{$refunds}", 0, 191),
            'type' => "payment.{$status}",
            'payment_id' => $data['id'],
        ];
    }

    /**
     * The webhook body is only a hint: the payment is re-read from Tabby, so
     * forged, stale or out-of-order deliveries cannot change anything.
     */
    public function normalizeEvent(array $event): GatewayEvent
    {
        $id = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        $paymentId = $event['payment_id'] ?? null;

        if (! is_string($paymentId) || $paymentId === '') {
            return new GatewayEvent($id, $type);
        }

        $local = Payment::query()->where('provider', self::CODE)->where('provider_reference', $paymentId)->latest('id')->first();

        if ($local === null) {
            // Not ours (another integration on the same account), or our checkout is not committed yet: retried.
            return new GatewayEvent($id, $type);
        }

        $tabby = $this->fetch($paymentId);
        $refund = $this->latestRefund($tabby);

        if ($refund !== null && ! $local->isOpen()) {
            return new GatewayEvent($id, $type, refund: $refund);
        }

        return new GatewayEvent($id, $type, payment: $this->update($tabby, $local));
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $paymentId = $payment->provider_payment_id ?? $payment->provider_reference
            ?? throw new GatewayException('This payment has no Tabby reference and cannot be refunded through Tabby.');

        $response = $this->api->request('post', '/api/v2/payments/'.rawurlencode($paymentId).'/refunds', [
            'amount' => $amount->toDisplayDecimal(),
            'reference_id' => mb_substr($idempotencyKey, 0, 100),
        ], retry: true);

        foreach (array_reverse((array) ($response['refunds'] ?? [])) as $refund) {
            if (is_array($refund) && ($refund['reference_id'] ?? null) === mb_substr($idempotencyKey, 0, 100)) {
                return $this->refundUpdate($refund, $paymentId, (string) ($response['currency'] ?? $amount->currency()));
            }
        }

        throw new GatewayException('Tabby accepted the refund but did not return it. Check the Tabby dashboard before retrying.');
    }

    /**
     * Confirms the keys work (admin "Test connection").
     *
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        $this->api->request('get', '/api/v1/webhooks', retry: true);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function fetch(string $paymentId): array
    {
        if ($paymentId === '') {
            throw new GatewayException('Missing Tabby payment id.');
        }

        return $this->api->request('get', '/api/v2/payments/'.rawurlencode($paymentId), retry: true);
    }

    /**
     * @param  array<string, mixed>  $tabby  a payment object from the Tabby API
     *
     * @throws GatewayException
     */
    private function update(array $tabby, ?Payment $local, bool $mayCapture = true): PaymentUpdate
    {
        $id = (string) ($tabby['id'] ?? '');
        $amount = $this->money($tabby['amount'] ?? null, $tabby['currency'] ?? null);

        switch ($this->status($tabby)) {
            case 'AUTHORIZED':
                $captured = $this->captured($tabby);

                if ($captured->isPositive()) {
                    // Our capture is being settled; it closes once the full amount is captured.
                    return new PaymentUpdate($amount !== null && $captured->equals($amount) ? PaymentUpdateKind::Succeeded : PaymentUpdateKind::Processing, $id, $id, $captured);
                }

                if ($local === null || ! $mayCapture) {
                    return new PaymentUpdate(PaymentUpdateKind::Pending, $id, $id);
                }

                if ($local->order->status === OrderStatus::Cancelled || in_array($local->status, [Payment::STATUS_EXPIRED, Payment::STATUS_FAILED], true)) {
                    // Paid after the order was cancelled or replaced: release the customer instead of capturing.
                    $this->close($id);

                    return new PaymentUpdate(PaymentUpdateKind::Expired, $id, $id);
                }

                if (! $local->isOpen()) {
                    return new PaymentUpdate(PaymentUpdateKind::Pending, $id, $id);
                }

                if ($amount === null || ! $amount->equals($local->money())) {
                    // Not captured: the platform flags the mismatch for staff to resolve in Tabby.
                    return new PaymentUpdate(PaymentUpdateKind::Succeeded, $id, $id, $amount);
                }

                return $this->update($this->capture($local, $id, $amount), $local, mayCapture: false);

            case 'CLOSED':
                $captured = $this->captured($tabby);

                // Closed without a capture means the payment was cancelled.
                return $captured->isPositive()
                    ? new PaymentUpdate(PaymentUpdateKind::Succeeded, $id, $id, $captured)
                    : new PaymentUpdate(PaymentUpdateKind::Expired, $id, $id);

            case 'REJECTED':
                return new PaymentUpdate(PaymentUpdateKind::Failed, $id, $id, failureReason: 'Tabby did not approve this payment.');

            case 'EXPIRED':
                return new PaymentUpdate(PaymentUpdateKind::Expired, $id, $id);

            default:
                return new PaymentUpdate(PaymentUpdateKind::Pending, $id, $id);
        }
    }

    /**
     * Captures the full amount once. The lock keeps the return page and a
     * webhook from racing; the reference_id makes Tabby ignore a repeat.
     *
     * @return array<string, mixed> the payment after capture
     *
     * @throws GatewayException
     */
    private function capture(Payment $local, string $paymentId, Money $amount): array
    {
        $lock = Cache::lock("tabby-capture:{$paymentId}", 30);

        try {
            $lock->block(10);
        } catch (LockTimeoutException) {
            throw new GatewayException('Another request is capturing this Tabby payment. Try again shortly.', retryable: true);
        }

        try {
            $current = $this->fetch($paymentId);

            if ($this->status($current) !== 'AUTHORIZED' || $this->captured($current)->isPositive()) {
                return $current;
            }

            $response = $this->api->send('post', '/api/v2/payments/'.rawurlencode($paymentId).'/captures', [
                'amount' => $amount->toDisplayDecimal(),
                'reference_id' => "capture-{$local->id}",
            ], retry: true);

            // A 400 here usually means "already captured/closed": re-read the truth instead of failing.
            if ($response->failed() && $response->status() !== 400) {
                throw TabbyApi::error($response);
            }

            return $this->fetch($paymentId);
        } finally {
            $lock->release();
        }
    }

    private function close(string $paymentId): void
    {
        try {
            $this->api->request('post', '/api/v2/payments/'.rawurlencode($paymentId).'/close', retry: true);
        } catch (Throwable $e) {
            Log::warning('Tabby payment could not be closed.', ['payment' => $paymentId, 'reason' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $tabby
     */
    private function status(array $tabby): string
    {
        return strtoupper((string) ($tabby['status'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $tabby
     */
    private function captured(array $tabby): Money
    {
        $market = $this->settings->market();
        $currency = (string) ($tabby['currency'] ?? ($market === null ? 'AED' : $market->currency));
        $total = Money::zero($currency);

        foreach ((array) ($tabby['captures'] ?? []) as $capture) {
            $amount = is_array($capture) ? $this->money($capture['amount'] ?? null, $currency) : null;
            $total = $amount === null ? $total : $total->plus($amount);
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $tabby
     */
    private function latestRefund(array $tabby): ?RefundUpdate
    {
        $refunds = array_values(array_filter((array) ($tabby['refunds'] ?? []), 'is_array'));

        return $refunds === [] ? null : $this->refundUpdate(end($refunds), (string) ($tabby['id'] ?? ''), (string) ($tabby['currency'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function refundUpdate(array $refund, string $paymentId, string $currency): RefundUpdate
    {
        return new RefundUpdate(
            (string) ($refund['id'] ?? ''),
            $paymentId,
            $this->money($refund['amount'] ?? null, $currency) ?? Money::zero($currency !== '' ? $currency : 'AED'),
            // Tabby refunds are processed instantly; a returned refund is final.
            RefundUpdate::SUCCEEDED,
        );
    }

    private function money(mixed $amount, mixed $currency): ?Money
    {
        if (! is_string($currency) || TabbyMarket::for($currency) === null || ! (is_string($amount) || is_int($amount) || is_float($amount))
            || ! preg_match('/^\d{1,13}(\.\d{1,4})?$/', (string) $amount)) {
            return null;
        }

        return Money::of((string) $amount, strtoupper($currency));
    }
}
