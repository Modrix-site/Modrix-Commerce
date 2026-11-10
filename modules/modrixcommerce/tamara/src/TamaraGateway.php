<?php

namespace EcoModular\Tamara;

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
 * Tamara "Pay in 4" on Tamara's hosted checkout.
 *
 * Tamara approves an order first and expects the merchant to authorise and
 * then capture. The platform has no separate capture step, so verification
 * captures: when Tamara reports an APPROVED or AUTHORISED order that matches
 * one of our open payments in amount and currency, it is authorised (if
 * needed), captured in full and reported as paid. A cache lock plus a status
 * re-read make a retry from the return page, a webhook or the reconcile job
 * safe: a payment is never captured twice.
 */
final class TamaraGateway implements PaymentGateway
{
    public const CODE = 'tamara';

    /** Header carrying the notification token Tamara echoes on every webhook. */
    public const WEBHOOK_HEADER = 'tamara-token';

    /** Tamara closes an unpaid checkout session after a short window. */
    public const SESSION_MINUTES = 30;

    public function __construct(
        private readonly TamaraSettings $settings,
        private readonly TamaraApi $api,
        private readonly TamaraPayload $payload,
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
        return TamaraCopy::get('method');
    }

    public function instructions(): string
    {
        return TamaraCopy::get('description');
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
        $response = $this->api->request('post', '/checkout', $this->payload->session($order, $payment, $returnUrl, $cancelUrl));

        $url = $response['checkout_url'] ?? null;
        $orderId = $response['order_id'] ?? null;

        if (! is_string($url) || ! is_string($orderId) || $orderId === '') {
            throw new GatewayException(
                'Tamara did not approve this order.',
                customerMessage: TamaraCopy::rejection(is_string($response['rejection_reason'] ?? null) ? $response['rejection_reason'] : null),
            );
        }

        if (! $this->isTrustedCheckoutUrl($url)) {
            throw new GatewayException('Tamara returned a checkout page on an unexpected host.');
        }

        return new PaymentSession($orderId, $url, now()->addMinutes(self::SESSION_MINUTES));
    }

    /**
     * Cancels an unused authorisation when an unpaid order is abandoned or
     * cancelled. Session-only (never captured) orders can simply be cancelled
     * at Tamara; an untouched checkout expires on its own.
     */
    public function expireSession(Payment $payment): void
    {
        if ($payment->provider_reference === null) {
            return;
        }

        try {
            $order = $this->fetch($payment->provider_reference);

            if (in_array($this->status($order), ['APPROVED', 'NEW', 'AUTHORISED'], true) && $this->captured($order, $this->currency($order))->isZero()) {
                $this->cancel($payment->provider_reference);
            }
        } catch (GatewayException $e) {
            Log::info('Tamara payment could not be cancelled.', ['payment_id' => $payment->id, 'reason' => $e->getMessage()]);
        }
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        return $this->update($this->fetch((string) $payment->provider_reference), $payment);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $token = $this->settings->notificationToken();

        if ($token === null) {
            throw new InvalidWebhook('Tamara webhooks are not configured.');
        }

        $given = $headers[strtolower(self::WEBHOOK_HEADER)] ?? '';

        if ($given === '' || ! hash_equals($token, $given)) {
            throw new InvalidWebhook('Tamara notification token does not match.');
        }

        $data = json_decode($payload, true);
        $orderId = is_array($data) ? ($data['order_id'] ?? null) : null;

        if (! is_array($data) || ! is_string($orderId) || $orderId === '') {
            throw new InvalidWebhook('Tamara webhook payload is malformed.');
        }

        $status = strtolower((string) ($data['order_status'] ?? $data['status'] ?? ''));
        $event = (string) ($data['event_type'] ?? "order.{$status}");

        // Tamara sends no event id: one event per order state.
        return [
            'id' => mb_substr("{$orderId}:{$event}:{$status}", 0, 191),
            'type' => $event,
            'payment_id' => $orderId,
            'status' => $status,
        ];
    }

    /**
     * The webhook body is only a hint: the order is re-read from Tamara, so
     * forged, stale or out-of-order deliveries cannot change anything.
     */
    public function normalizeEvent(array $event): GatewayEvent
    {
        $id = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        $orderId = $event['payment_id'] ?? null;

        if (! is_string($orderId) || $orderId === '') {
            return new GatewayEvent($id, $type);
        }

        $local = Payment::query()->where('provider', self::CODE)->where('provider_reference', $orderId)->latest('id')->first();

        if ($local === null) {
            // Not ours (another integration on the same account), or our checkout is not committed yet: retried.
            return new GatewayEvent($id, $type);
        }

        $order = $this->fetch($orderId);
        $refund = $this->latestRefund($order);

        if ($refund !== null && ! $local->isOpen()) {
            return new GatewayEvent($id, $type, refund: $refund);
        }

        return new GatewayEvent($id, $type, payment: $this->update($order, $local));
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $orderId = $payment->provider_reference ?? $payment->provider_payment_id
            ?? throw new GatewayException('This payment has no Tamara order and cannot be refunded through Tamara.');

        $reference = mb_substr($idempotencyKey, 0, 100);

        $response = $this->api->request('post', '/payments/refund', [
            'order_id' => $orderId,
            'total_amount' => $amount->toDisplayDecimal(),
            'comment' => "Refund for order {$payment->order->number}",
            'reference_id' => $reference,
        ], retry: true);

        // Some responses return the refund directly, others the updated order.
        $candidate = $this->findRefund($response, $reference);

        if ($candidate === null) {
            $candidate = $this->findRefund($this->fetch($orderId), $reference);
        }

        if ($candidate === null) {
            throw new GatewayException('Tamara accepted the refund but did not return it. Check the Tamara dashboard before retrying.');
        }

        return $this->refundUpdate($candidate, $orderId, $amount->currency());
    }

    /**
     * Confirms the API token works (admin "Test connection"). A missing order
     * (404) proves the credentials were accepted; a 401/403 does not.
     *
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        $response = $this->api->send('get', '/orders/00000000-0000-0000-0000-000000000000', retry: true);

        if (in_array($response->status(), [401, 403], true)) {
            throw TamaraApi::error($response);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function fetch(string $orderId): array
    {
        if ($orderId === '') {
            throw new GatewayException('Missing Tamara order id.');
        }

        return $this->api->request('get', '/orders/'.rawurlencode($orderId), retry: true);
    }

    /**
     * @param  array<string, mixed>  $order  an order object from the Tamara API
     *
     * @throws GatewayException
     */
    private function update(array $order, ?Payment $local, bool $mayCapture = true): PaymentUpdate
    {
        $id = (string) ($order['order_id'] ?? $order['id'] ?? '');
        $currency = $this->currency($order);
        $amount = $this->money($order['total_amount'] ?? $order['amount'] ?? null, $order['currency'] ?? $currency);

        switch ($this->status($order)) {
            case 'APPROVED':
            case 'AUTHORISED':
            case 'AUTHORIZED':
                if ($local === null || ! $mayCapture) {
                    return new PaymentUpdate(PaymentUpdateKind::Pending, $id, $id);
                }

                if ($local->order->status === OrderStatus::Cancelled || in_array($local->status, [Payment::STATUS_EXPIRED, Payment::STATUS_FAILED], true)) {
                    // Approved after the order was cancelled or replaced: release the customer instead of capturing.
                    $this->cancel($id);

                    return new PaymentUpdate(PaymentUpdateKind::Expired, $id, $id);
                }

                if (! $local->isOpen()) {
                    return new PaymentUpdate(PaymentUpdateKind::Pending, $id, $id);
                }

                if ($amount === null || ! $amount->equals($local->money())) {
                    // Not captured: the platform flags the mismatch for staff to resolve in Tamara.
                    return new PaymentUpdate(PaymentUpdateKind::Succeeded, $id, $id, $amount);
                }

                return $this->update($this->capture($local, $id, $amount), $local, mayCapture: false);

            case 'CAPTURED':
                return new PaymentUpdate(PaymentUpdateKind::Succeeded, $id, $id, $amount ?? $this->captured($order, $currency));

            case 'PARTIALLY_CAPTURED':
                return new PaymentUpdate(PaymentUpdateKind::Processing, $id, $id, $this->captured($order, $currency));

            case 'DECLINED':
            case 'FAILED':
                return new PaymentUpdate(PaymentUpdateKind::Failed, $id, $id, failureReason: 'Tamara did not approve this payment.');

            case 'EXPIRED':
            case 'CANCELED':
            case 'CANCELLED':
                return new PaymentUpdate(PaymentUpdateKind::Expired, $id, $id);

            default:
                return new PaymentUpdate(PaymentUpdateKind::Pending, $id, $id);
        }
    }

    /**
     * Authorises (when needed) and captures the full amount once. The lock
     * keeps the return page and a webhook from racing.
     *
     * @return array<string, mixed> the order after capture
     *
     * @throws GatewayException
     */
    private function capture(Payment $local, string $orderId, Money $amount): array
    {
        $lock = Cache::lock("tamara-capture:{$orderId}", 30);

        try {
            $lock->block(10);
        } catch (LockTimeoutException) {
            throw new GatewayException('Another request is capturing this Tamara payment. Try again shortly.', retryable: true);
        }

        try {
            $current = $this->fetch($orderId);

            if ($this->status($current) === 'CAPTURED') {
                return $current;
            }

            if (in_array($this->status($current), ['APPROVED', 'NEW'], true)) {
                $authorised = $this->api->send('post', '/orders/'.rawurlencode($orderId).'/authorise', ['order_id' => $orderId], retry: true);

                // A 4xx here usually means "already authorised": re-read the truth instead of failing.
                if ($authorised->failed() && $authorised->status() !== 400) {
                    throw TamaraApi::error($authorised);
                }
            }

            $captured = $this->api->send('post', '/orders/'.rawurlencode($orderId).'/capture', [
                'order_id' => $orderId,
                'total_amount' => $amount->toDisplayDecimal(),
                'reference_id' => "capture-{$local->id}",
            ], retry: true);

            // A 400/409 usually means "already captured": re-read the truth instead of failing.
            if ($captured->failed() && ! in_array($captured->status(), [400, 409], true)) {
                throw TamaraApi::error($captured);
            }

            return $this->fetch($orderId);
        } finally {
            $lock->release();
        }
    }

    private function cancel(string $orderId): void
    {
        try {
            $this->api->request('post', '/orders/'.rawurlencode($orderId).'/cancel', ['order_id' => $orderId], retry: true);
        } catch (Throwable $e) {
            Log::warning('Tamara order could not be cancelled.', ['order' => $orderId, 'reason' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function status(array $order): string
    {
        return strtoupper((string) ($order['status'] ?? $order['order_status'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function currency(array $order): string
    {
        $currency = $order['currency'] ?? null;

        if (! is_string($currency) || TamaraMarket::for($currency) === null) {
            $market = $this->settings->market();

            return $market === null ? 'AED' : $market->currency;
        }

        return strtoupper($currency);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function captured(array $order, string $currency): Money
    {
        return $this->money($order['captured_amount'] ?? null, $currency) ?? Money::zero($currency);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function latestRefund(array $order): ?RefundUpdate
    {
        $refunds = array_values(array_filter((array) ($order['refunds'] ?? []), 'is_array'));

        return $refunds === [] ? null : $this->refundUpdate(end($refunds), (string) ($order['order_id'] ?? $order['id'] ?? ''), $this->currency($order));
    }

    /**
     * Finds a refund (in a refund response or an order object) by reference, else the last one.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function findRefund(array $payload, string $reference): ?array
    {
        $refunds = array_values(array_filter((array) ($payload['refunds'] ?? []), 'is_array'));

        if ($refunds === []) {
            return isset($payload['refund_id']) || isset($payload['amount']) ? $payload : null;
        }

        foreach (array_reverse($refunds) as $refund) {
            if (($refund['reference_id'] ?? null) === $reference) {
                return $refund;
            }
        }

        return end($refunds) ?: null;
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function refundUpdate(array $refund, string $orderId, string $currency): RefundUpdate
    {
        $status = strtolower((string) ($refund['status'] ?? 'succeeded'));

        return new RefundUpdate(
            (string) ($refund['refund_id'] ?? $refund['id'] ?? ''),
            $orderId,
            $this->money($refund['amount'] ?? $refund['total_amount'] ?? null, $currency) ?? Money::zero($currency),
            // Tamara refunds are processed as they are accepted; an unknown status is treated as pending.
            match ($status) {
                'failed', 'declined' => RefundUpdate::FAILED,
                'pending', 'processing', 'created' => RefundUpdate::PENDING,
                default => RefundUpdate::SUCCEEDED,
            },
        );
    }

    private function isTrustedCheckoutUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && ($host === 'tamara.co' || str_ends_with($host, '.tamara.co'));
    }

    private function money(mixed $amount, mixed $currency): ?Money
    {
        // Tamara returns amounts either as a number or as {amount, currency}.
        if (is_array($amount)) {
            $currency = $amount['currency'] ?? $currency;
            $amount = $amount['amount'] ?? null;
        }

        if (! is_string($currency) || TamaraMarket::for($currency) === null || ! (is_string($amount) || is_int($amount) || is_float($amount))
            || ! preg_match('/^\d{1,13}(\.\d{1,4})?$/', (string) $amount)) {
            return null;
        }

        return Money::of((string) $amount, strtoupper($currency));
    }
}
