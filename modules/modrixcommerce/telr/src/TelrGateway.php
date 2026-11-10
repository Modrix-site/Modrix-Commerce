<?php

namespace EcoModular\Telr;

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
use Throwable;

/**
 * Telr hosted payment page.
 *
 * The customer enters card details on Telr (full-page redirect, or an iframe
 * on this store when that display is selected). This store never sees card
 * data. An order is marked paid only after order.json "check" confirms a
 * captured sale for the same amount, currency and test mode. The return URL
 * and the transaction-advice webhook both trigger that check.
 */
final class TelrGateway implements PaymentGateway
{
    public const CODE = 'telr';

    /** Telr order references are short-lived; send the customer promptly. */
    public const SESSION_MINUTES = 30;

    public function __construct(
        private readonly TelrSettings $settings,
        private readonly TelrApi $api,
        private readonly TelrPayload $payload,
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
        return 'Telr';
    }

    public function instructions(): string
    {
        return $this->settings->iframe()
            ? 'You pay in a secure Telr form on this page. Your card details are entered there and are never stored by this store.'
            : 'You will be redirected to Telr\'s secure payment page. Your card details are entered there and are never stored by this store.';
    }

    public function isConfigured(): bool
    {
        return $this->settings->ready();
    }

    public function isAvailable(PricedCart $cart): bool
    {
        return $cart->grandTotal()->isPositive();
    }

    public function allowsFulfillmentBeforePayment(): bool
    {
        return false;
    }

    public function createSession(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): PaymentSession
    {
        if ($this->settings->storeId() === null || $this->settings->authKey() === null) {
            throw new GatewayException('Telr is not configured: add the store ID and authentication key.');
        }

        if ($this->settings->iframe() && ! str_starts_with($returnUrl, 'https://')) {
            throw new GatewayException(
                'Telr iframe checkout requires the store to be served over https.',
                customerMessage: 'Card payment is unavailable until the store is using a secure (https) address.',
            );
        }

        $response = $this->api->order($this->payload->create($order, $payment, $returnUrl, $cancelUrl));

        if (isset($response['error'])) {
            throw new GatewayException($this->api->errorMessage($response));
        }

        $ref = $response['order']['ref'] ?? null;
        $url = $response['order']['url'] ?? null;

        if (! is_string($ref) || $ref === '' || ! is_string($url) || ! TelrCheckoutLink::isProcessUrl($url)) {
            throw new GatewayException('Telr returned an unexpected payment page.');
        }

        $expiresAt = now()->addMinutes(self::SESSION_MINUTES);
        TelrCheckoutLink::remember($payment, $url, $expiresAt);

        $redirect = $this->settings->iframe()
            ? route('modrixcommerce.telr.frame', ['payment' => $payment->id])
            : $url;

        return new PaymentSession($ref, $redirect, $expiresAt);
    }

    /**
     * Unpaid Telr orders expire on their own. There is no cancel method on
     * the hosted payment page API.
     */
    public function expireSession(Payment $payment): void
    {
        //
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $ref = (string) $payment->provider_reference;

        if ($ref === '') {
            throw new GatewayException('Missing Telr order reference.');
        }

        $response = $this->api->order([
            'method' => 'check',
            'store' => $this->settings->storeId(),
            'authkey' => $this->settings->authKey(),
            'order' => ['ref' => $ref],
        ]);

        if (isset($response['error'])) {
            throw new GatewayException($this->api->errorMessage($response), retryable: true);
        }

        $order = $response['order'] ?? null;

        if (! is_array($order)) {
            throw new GatewayException('Telr returned an unreadable order status.', retryable: true);
        }

        return $this->update($ref, $order);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $secret = $this->settings->webhookSecret();

        if ($secret === null || $this->settings->storeId() === null) {
            throw new InvalidWebhook('Telr webhooks are not configured.');
        }

        $fields = self::fields($payload);

        if (! TelrSignature::matches($fields, $secret)) {
            throw new InvalidWebhook('Telr webhook signature does not match.');
        }

        $store = (string) ($fields['tran_store'] ?? '');

        if ($store !== (string) $this->settings->storeId()) {
            throw new InvalidWebhook('Telr webhook is for a different store.');
        }

        $test = (string) ($fields['tran_test'] ?? '');

        if (($test === '1') !== $this->settings->testMode()) {
            throw new InvalidWebhook('Telr webhook test mode does not match the store configuration.');
        }

        $tranRef = trim((string) ($fields['tran_ref'] ?? ''));
        $type = strtolower(trim((string) ($fields['tran_type'] ?? '')));
        $status = strtoupper(trim((string) ($fields['tran_status'] ?? '')));

        if ($tranRef === '' || $type === '' || $status === '') {
            throw new InvalidWebhook('Telr webhook payload is incomplete.');
        }

        $cartId = trim((string) ($fields['tran_cartid'] ?? ''));
        $orderRef = trim((string) ($fields['tran_order'] ?? ''));

        return [
            'id' => mb_substr("{$tranRef}:{$type}:{$status}:".trim((string) ($fields['tran_amount'] ?? '')), 0, 191),
            'type' => "{$type}.{$status}",
            'kind' => $type === 'refund' ? 'refund' : 'payment',
            'cart_id' => $cartId,
            'order_ref' => $orderRef,
            'tran_ref' => $tranRef,
            'tran_prevref' => trim((string) ($fields['tran_prevref'] ?? '')),
            'tran_firstref' => trim((string) ($fields['tran_firstref'] ?? '')),
            'tran_status' => $status,
            'tran_amount' => trim((string) ($fields['tran_amount'] ?? '')),
            'tran_currency' => strtoupper(trim((string) ($fields['tran_currency'] ?? ''))),
            'tran_authmessage' => mb_substr(trim((string) ($fields['tran_authmessage'] ?? '')), 0, 180),
        ];
    }

    /**
     * A sale advice is only a hint: the order is re-read from Telr. A refund
     * advice is applied from the signed fields, because the check API reports
     * the original sale rather than each refund.
     */
    public function normalizeEvent(array $event): GatewayEvent
    {
        $id = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if (($event['kind'] ?? '') === 'refund') {
            $refund = $this->refundFromAdvice($event);

            return new GatewayEvent($id, $type, refund: $refund);
        }

        $payment = $this->paymentFromAdvice($event);

        if ($payment === null) {
            return new GatewayEvent($id, $type);
        }

        return new GatewayEvent($id, $type, payment: $this->retrieve($payment));
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $tranRef = $payment->provider_payment_id
            ?? throw new GatewayException('This payment has no Telr transaction reference and cannot be refunded through Telr.');

        $cartId = mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', $idempotencyKey) ?? '', 0, 63);

        $result = $this->api->remote([
            'type' => 'refund',
            'class' => 'ecom',
            'cartid' => $cartId !== '' ? $cartId : 'refund-'.$payment->id,
            'description' => mb_substr('Refund '.($payment->order->number ?? $payment->id), 0, 63),
            'currency' => $amount->currency(),
            'amount' => $amount->toDisplayDecimal(),
            'ref' => $tranRef,
            'test' => $this->settings->testMode() ? '1' : '0',
        ]);

        $status = match ($result['status']) {
            'A' => RefundUpdate::SUCCEEDED,
            'H' => RefundUpdate::PENDING,
            default => throw new GatewayException(
                'Telr did not refund this payment'.($result['message'] !== '' ? ": {$result['message']}" : '.').' Check the Telr dashboard before trying again.',
            ),
        };

        return new RefundUpdate($result['tranref'], $tranRef, $amount, $status);
    }

    /**
     * Confirms the store ID and authentication key (admin "Test connection").
     * A missing order is a successful authentication; a rejected store or key is not.
     *
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        if ($this->settings->storeId() === null || $this->settings->authKey() === null) {
            throw new GatewayException('Save the Telr store ID and authentication key first.');
        }

        $response = $this->api->order([
            'method' => 'check',
            'store' => $this->settings->storeId(),
            'authkey' => $this->settings->authKey(),
            'order' => ['ref' => '00000000000000000000000000000001'],
        ]);

        $message = is_array($response['error'] ?? null) ? (string) ($response['error']['message'] ?? '') : '';

        if (TelrApi::credentialError($message)) {
            throw new GatewayException('Telr rejected the store ID or authentication key.');
        }

        if ($message === '' && ! isset($response['order']) && ! isset($response['method'])) {
            throw new GatewayException('Telr returned an unreadable response.');
        }
    }

    /**
     * @param  array<string, mixed>  $order  the order object from a check response
     */
    private function update(string $ref, array $order): PaymentUpdate
    {
        $code = (int) ($order['status']['code'] ?? 0);
        $text = is_string($order['status']['text'] ?? null) ? $order['status']['text'] : '';
        $transaction = is_array($order['transaction'] ?? null) ? $order['transaction'] : [];
        $tranRef = is_string($transaction['ref'] ?? null) && $transaction['ref'] !== '' ? $transaction['ref'] : null;
        $test = (string) ($order['test'] ?? '0') === '1';

        if ($test !== $this->settings->testMode()) {
            return new PaymentUpdate(
                PaymentUpdateKind::Failed,
                $ref,
                $tranRef,
                failureReason: 'Telr test mode does not match the store configuration.',
            );
        }

        $amount = $this->money($order['amount'] ?? null, $order['currency'] ?? null);
        $reason = is_string($transaction['message'] ?? null) && $transaction['message'] !== ''
            ? mb_substr($transaction['message'], 0, 180)
            : ($text !== '' ? $text : null);

        $kind = match ($code) {
            3 => PaymentUpdateKind::Succeeded,
            2 => PaymentUpdateKind::Processing,
            -1, -2, -4 => PaymentUpdateKind::Expired,
            -3 => PaymentUpdateKind::Failed,
            default => PaymentUpdateKind::Pending,
        };

        return new PaymentUpdate(
            $kind,
            $ref,
            $tranRef,
            in_array($kind, [PaymentUpdateKind::Succeeded, PaymentUpdateKind::Processing], true) ? $amount : null,
            $kind === PaymentUpdateKind::Failed ? ($reason ?? 'Telr declined this payment.') : null,
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function paymentFromAdvice(array $event): ?Payment
    {
        $orderRef = (string) ($event['order_ref'] ?? '');

        if ($orderRef !== '') {
            $byRef = Payment::query()->where('provider', self::CODE)->where('provider_reference', $orderRef)->latest('id')->first();

            if ($byRef !== null) {
                return $byRef;
            }
        }

        $cartId = (string) ($event['cart_id'] ?? '');

        if (preg_match('/^p(\d+)$/', $cartId, $match) !== 1) {
            return null;
        }

        $payment = Payment::query()->where('provider', self::CODE)->whereKey((int) $match[1])->first();

        if ($payment === null) {
            throw new GatewayException('Telr webhook arrived before the payment was saved.', retryable: true);
        }

        if ($payment->provider_reference === null || $payment->provider_reference === '') {
            throw new GatewayException('Telr webhook arrived before the order reference was saved.', retryable: true);
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function refundFromAdvice(array $event): ?RefundUpdate
    {
        $original = (string) ($event['tran_prevref'] ?? '');

        if ($original === '') {
            $first = (string) ($event['tran_firstref'] ?? '');
            $original = $first !== (string) ($event['tran_ref'] ?? '') ? $first : '';
        }

        if ($original === '') {
            Log::info('Telr refund advice had no original transaction reference.');

            return null;
        }

        $amount = $this->money($event['tran_amount'] ?? null, $event['tran_currency'] ?? null);

        if ($amount === null || ! $amount->isPositive()) {
            Log::info('Telr refund advice had no usable amount.');

            return null;
        }

        $status = match ((string) ($event['tran_status'] ?? '')) {
            'A' => RefundUpdate::SUCCEEDED,
            'H' => RefundUpdate::PENDING,
            default => RefundUpdate::FAILED,
        };

        return new RefundUpdate(
            (string) ($event['tran_ref'] ?? ''),
            $original,
            $amount,
            $status,
            $status === RefundUpdate::FAILED ? ((string) ($event['tran_authmessage'] ?? '') ?: 'Telr did not refund this payment.') : null,
        );
    }

    private function money(mixed $amount, mixed $currency): ?Money
    {
        if (! is_string($currency) || ! (is_string($amount) || is_int($amount)) || ! preg_match('/^\d{1,13}(\.\d{1,4})?$/', (string) $amount)) {
            return null;
        }

        try {
            return Money::of((string) $amount, strtoupper($currency));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidWebhook
     */
    private static function fields(string $payload): array
    {
        $trimmed = ltrim($payload);

        if (str_starts_with($trimmed, '{')) {
            try {
                $data = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                throw new InvalidWebhook('Telr webhook payload is malformed.');
            }

            if (! is_array($data)) {
                throw new InvalidWebhook('Telr webhook payload is malformed.');
            }

            return $data;
        }

        $data = [];
        parse_str($payload, $data);

        if ($data === [] || ! is_string($data['tran_check'] ?? null)) {
            throw new InvalidWebhook('Telr webhook payload is malformed.');
        }

        return $data;
    }
}
