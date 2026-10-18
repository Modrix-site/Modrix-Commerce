<?php

namespace EcoModular\AuthorizeNet;

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

/**
 * Authorize.Net Accept Hosted. The customer pays on Authorize.Net's form.
 * This store marks the order paid only after getTransactionDetails confirms
 * a capture for the same invoice and amount. The browser return is not proof.
 *
 * @see https://developer.authorize.net/api/reference/features/accept-hosted.html
 * @see https://developer.authorize.net/api/reference/features/webhooks.html
 */
final class AuthorizeNetGateway implements PaymentGateway
{
    public const CODE = 'authorize_net';

    /** Accept Hosted tokens are valid for 15 minutes. */
    public const SESSION_MINUTES = 15;

    /** Stop offering a token shortly before Authorize.Net expires it. */
    public const TOKEN_MINUTES = 13;

    public function __construct(
        private readonly AuthorizeNetSettings $settings,
        private readonly AuthorizeNetApi $api,
        private readonly AuthorizeNetPayload $payload,
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
        return 'Authorize.Net';
    }

    public function instructions(): string
    {
        return 'You will continue to Authorize.Net\'s secure payment page. Card details are entered there and are never stored by this store.';
    }

    public function isConfigured(): bool
    {
        return $this->settings->ready();
    }

    public function isAvailable(PricedCart $cart): bool
    {
        try {
            $value = AuthorizeNetAmount::toValue($cart->grandTotal());

            return $cart->grandTotal()->isPositive() && $value !== '0.00';
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
        $created = $this->api->call('getHostedPaymentPageRequest', $this->payload->page($order, $payment, $returnUrl, $cancelUrl));
        $token = $created['token'] ?? null;

        if (! is_string($token) || ! AuthorizeNetEndpoint::isToken($token)) {
            throw new GatewayException('Authorize.Net did not return a payment form.');
        }

        $formUrl = $this->settings->formUrl();

        if (! AuthorizeNetEndpoint::isFormUrl($formUrl)) {
            throw new GatewayException('Authorize.Net payment form address is not allowed.');
        }

        AuthorizeNetFormToken::put($payment->id, $token);

        return new PaymentSession('p'.$payment->id, route('ecomodular.authorize-net.handoff', $payment), now()->addMinutes(self::SESSION_MINUTES));
    }

    public function expireSession(Payment $payment): void
    {
        Cache::forget('authorizenet:form:'.$payment->id);
    }

    public function retrieve(Payment $payment): PaymentUpdate
    {
        $reference = (string) $payment->provider_reference;

        if (preg_match('/^p\d{1,18}$/', $reference) !== 1) {
            throw new GatewayException('Missing Authorize.Net invoice reference.');
        }

        $transId = $this->transactionId($payment, $reference);

        if ($transId === null) {
            $expired = $payment->expires_at !== null && $payment->expires_at->isPast();

            return new PaymentUpdate($expired ? PaymentUpdateKind::Expired : PaymentUpdateKind::Pending, $reference);
        }

        return $this->fromDetails($reference, $this->details($transId), $payment->currency);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $key = $this->settings->signatureKey();

        if ($key === null) {
            throw new InvalidWebhook('Authorize.Net signature key is not configured.');
        }

        if (! AuthorizeNetSignature::matches($payload, (string) ($headers['x-anet-signature'] ?? ''), $key)) {
            throw new InvalidWebhook('Authorize.Net webhook signature did not match.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! is_string($event['notificationId'] ?? null) || $event['notificationId'] === '') {
            throw new InvalidWebhook('Authorize.Net webhook is not a notification.');
        }

        // The inbox stores `id` and `type`. Authorize.Net names those fields
        // notificationId and eventType.
        $event['id'] = $event['notificationId'];
        $event['type'] = (string) ($event['eventType'] ?? '');

        return $event;
    }

    public function normalizeEvent(array $event): GatewayEvent
    {
        $id = (string) ($event['notificationId'] ?? '');
        $type = (string) ($event['eventType'] ?? '');
        $body = is_array($event['payload'] ?? null) ? $event['payload'] : [];

        if ($type === 'net.authorize.payment.refund.created' || $type === 'net.authorize.payment.void.created') {
            return new GatewayEvent($id, $type, refund: $this->refundFromWebhook($type, $body));
        }

        if (! in_array($type, [
            'net.authorize.payment.authcapture.created',
            'net.authorize.payment.fraud.held',
            'net.authorize.payment.fraud.approved',
            'net.authorize.payment.fraud.declined',
        ], true)) {
            return new GatewayEvent($id, $type);
        }

        $reference = is_string($body['merchantReferenceId'] ?? null) ? $body['merchantReferenceId'] : '';
        $transId = isset($body['id']) ? (string) $body['id'] : '';

        if (preg_match('/^p\d{1,18}$/', $reference) !== 1 || preg_match('/^\d{1,20}$/', $transId) !== 1) {
            return new GatewayEvent($id, $type);
        }

        $payment = Payment::query()->where('provider', self::CODE)->where('provider_reference', $reference)->first();

        if ($payment === null) {
            return new GatewayEvent($id, $type);
        }

        return new GatewayEvent($id, $type, payment: $this->fromDetails($reference, $this->details($transId), $payment->currency));
    }

    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate
    {
        $transId = (string) $payment->provider_payment_id;

        if (preg_match('/^\d{1,20}$/', $transId) !== 1) {
            throw new GatewayException('This payment has no Authorize.Net transaction to refund.');
        }

        $cached = $this->cachedRefund($idempotencyKey, $transId, $amount);

        if ($cached !== null) {
            return $cached;
        }

        $transaction = $this->details($transId)['transaction'] ?? null;

        if (! is_array($transaction)) {
            throw new GatewayException('Authorize.Net did not return the transaction to refund.');
        }

        $paid = $this->amount($transaction, $amount->currency());
        $status = (string) ($transaction['transactionStatus'] ?? '');

        if ($amount->greaterThan($paid)) {
            throw new GatewayException('The refund is larger than the Authorize.Net transaction.');
        }

        if ($status === 'capturedPendingSettlement') {
            if (! $amount->equals($paid)) {
                throw new GatewayException('Authorize.Net can refund part of a payment only after it settles. Void the whole payment, or wait until it settles.');
            }

            $this->submit('voidTransaction', $transId, $idempotencyKey, []);
            $update = new RefundUpdate('void-'.$transId, $transId, $amount, RefundUpdate::SUCCEEDED);
            $this->rememberRefund($idempotencyKey, $update);

            return $update;
        }

        if ($status !== 'settledSuccessfully') {
            throw new GatewayException('This Authorize.Net payment cannot be refunded in its current state.');
        }

        $lastFour = AuthorizeNetTransaction::lastFour($this->cardNumber($transaction));

        if ($lastFour === null) {
            throw new GatewayException('Authorize.Net did not return the card number needed to refund this payment.');
        }

        $response = $this->submit('refundTransaction', $transId, $idempotencyKey, [
            'amount' => AuthorizeNetAmount::toValue($amount),
            'payment' => [
                'creditCard' => [
                    'cardNumber' => $lastFour,
                    'expirationDate' => 'XXXX',
                ],
            ],
        ]);
        $refundId = (string) ($response['transId'] ?? '');

        if (preg_match('/^\d{1,20}$/', $refundId) !== 1) {
            throw new GatewayException('Authorize.Net did not return a refund id.');
        }

        $update = new RefundUpdate(
            $refundId,
            $transId,
            $amount,
            ($response['responseCode'] ?? null) === '4' ? RefundUpdate::PENDING : RefundUpdate::SUCCEEDED,
        );
        $this->rememberRefund($idempotencyKey, $update);

        return $update;
    }

    /**
     * @throws GatewayException
     */
    private function transactionId(Payment $payment, string $reference): ?string
    {
        $known = (string) $payment->provider_payment_id;

        if (preg_match('/^\d{1,20}$/', $known) === 1) {
            return $known;
        }

        for ($page = 1; $page <= 5; $page++) {
            $list = $this->api->call('getUnsettledTransactionListRequest', [
                'sorting' => ['orderBy' => 'submitTimeUTC', 'orderDescending' => true],
                'paging' => ['limit' => 100, 'offset' => $page],
            ], retryable: true);
            $rows = $list['transactions'] ?? [];

            if (isset($rows['transId'])) {
                $rows = [$rows];
            }

            if (! is_array($rows) || $rows === []) {
                return null;
            }

            foreach ($rows as $row) {
                if (is_array($row) && ($row['invoiceNumber'] ?? null) === $reference && preg_match('/^\d{1,20}$/', (string) ($row['transId'] ?? '')) === 1) {
                    return (string) $row['transId'];
                }
            }

            if (count($rows) < 100) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function details(string $transId): array
    {
        return $this->api->call('getTransactionDetailsRequest', ['transId' => $transId], retryable: true);
    }

    /**
     * @param  array<string, mixed>  $response
     *
     * @throws GatewayException
     */
    private function fromDetails(string $reference, array $response, string $currency): PaymentUpdate
    {
        $transaction = $response['transaction'] ?? null;

        if (! is_array($transaction)) {
            throw new GatewayException('Authorize.Net did not return the transaction.');
        }

        $invoice = (string) ($transaction['order']['invoiceNumber'] ?? '');

        if ($invoice !== $reference) {
            throw new GatewayException('Authorize.Net transaction does not match this payment.');
        }

        $transId = (string) ($transaction['transId'] ?? '');

        if (preg_match('/^\d{1,20}$/', $transId) !== 1) {
            throw new GatewayException('Authorize.Net did not return a transaction id.');
        }

        $kind = AuthorizeNetTransaction::kind((string) ($transaction['transactionStatus'] ?? ''));
        $code = (string) ($transaction['responseCode'] ?? '');

        if (in_array($code, ['2', '3'], true)) {
            $kind = PaymentUpdateKind::Failed;
        } elseif ($code === '4' && $kind === PaymentUpdateKind::Succeeded) {
            $kind = PaymentUpdateKind::Processing;
        }

        $reason = is_string($transaction['responseReasonDescription'] ?? null) ? $transaction['responseReasonDescription'] : null;

        return new PaymentUpdate(
            $kind,
            $reference,
            $transId,
            $kind === PaymentUpdateKind::Succeeded ? $this->amount($transaction, $currency) : null,
            $kind === PaymentUpdateKind::Failed ? ($reason ?? 'The payment was declined.') : null,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     *
     * @throws GatewayException
     */
    private function refundFromWebhook(string $type, array $body): ?RefundUpdate
    {
        $transId = isset($body['id']) ? (string) $body['id'] : '';

        if (preg_match('/^\d{1,20}$/', $transId) !== 1) {
            return null;
        }

        $transaction = $this->details($transId)['transaction'] ?? null;

        if (! is_array($transaction)) {
            throw new GatewayException('Authorize.Net did not return the refunded transaction.', retryable: true);
        }

        $original = $type === 'net.authorize.payment.void.created'
            ? $transId
            : (string) ($transaction['refTransId'] ?? '');

        if (preg_match('/^\d{1,20}$/', $original) !== 1) {
            return null;
        }

        $payment = Payment::query()->where('provider', self::CODE)->where('provider_payment_id', $original)->first();

        if ($payment === null) {
            return null;
        }

        $reference = $type === 'net.authorize.payment.void.created' ? 'void-'.$original : $transId;

        return new RefundUpdate($reference, $original, $this->amount($transaction, $payment->currency), RefundUpdate::SUCCEEDED);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function submit(string $type, string $transId, string $idempotencyKey, array $extra): array
    {
        $refId = substr(hash('sha256', $idempotencyKey), 0, 20);
        $response = $this->api->call('createTransactionRequest', [
            'refId' => $refId,
            'transactionRequest' => ['transactionType' => $type, 'refTransId' => $transId, ...$extra],
        ]);
        $result = $response['transactionResponse'] ?? null;

        if (! is_array($result)) {
            throw new GatewayException('Authorize.Net did not return a transaction result.');
        }

        $code = (string) ($result['responseCode'] ?? '');

        if (! in_array($code, ['1', '4'], true)) {
            $error = $result['errors'][0]['errorText'] ?? null;

            throw new GatewayException(is_string($error) && $error !== '' ? $error : 'Authorize.Net declined the refund.');
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $transaction
     *
     * @throws GatewayException
     */
    private function amount(array $transaction, string $currency): Money
    {
        $value = $transaction['settleAmount'] ?? $transaction['authAmount'] ?? null;

        return AuthorizeNetAmount::fromApi($value, $currency);
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function cardNumber(array $transaction): ?string
    {
        $number = $transaction['payment']['creditCard']['cardNumber'] ?? null;

        return is_string($number) ? $number : null;
    }

    private function cachedRefund(string $idempotencyKey, string $transId, Money $amount): ?RefundUpdate
    {
        $cached = Cache::get($this->refundCacheKey($idempotencyKey));

        if (! is_array($cached) || ! is_string($cached['reference'] ?? null) || ! is_string($cached['status'] ?? null)) {
            return null;
        }

        return new RefundUpdate($cached['reference'], $transId, $amount, $cached['status']);
    }

    private function rememberRefund(string $idempotencyKey, RefundUpdate $update): void
    {
        Cache::put($this->refundCacheKey($idempotencyKey), [
            'reference' => $update->reference,
            'status' => $update->status,
        ], now()->addDays(7));
    }

    private function refundCacheKey(string $idempotencyKey): string
    {
        return 'authorizenet:refund:'.hash('sha256', $idempotencyKey);
    }
}
