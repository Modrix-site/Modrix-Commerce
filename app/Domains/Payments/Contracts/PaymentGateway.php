<?php

namespace App\Domains\Payments\Contracts;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Gateway\GatewayEvent;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Gateway\InvalidWebhook;
use App\Domains\Payments\Gateway\PaymentSession;
use App\Domains\Payments\Gateway\PaymentUpdate;
use App\Domains\Payments\Gateway\RefundUpdate;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;

/**
 * An online payment provider (stable public API).
 *
 * The customer pays on the provider's hosted page; the application never sees
 * card data. A gateway never decides that an order is paid by itself: it only
 * reports verified facts (webhooks, server-side session retrieval) that the
 * PaymentReconciler applies through the OrderWorkflow (ADR-027).
 *
 * Implementations must send an idempotency key with every request that
 * creates something at the provider, so a retried request cannot charge or
 * refund twice.
 */
interface PaymentGateway extends PaymentMethod
{
    /**
     * Stable provider identifier used in `payments.provider` and the webhook URL.
     */
    public function provider(): string;

    /**
     * Credentials are present and the merchant enabled the method.
     */
    public function isConfigured(): bool;

    /**
     * Creates a hosted payment session for the pending payment.
     *
     * @throws GatewayException
     */
    public function createSession(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): PaymentSession;

    /**
     * Best-effort: closes an unused session so it can no longer be paid.
     */
    public function expireSession(Payment $payment): void;

    /**
     * Asks the provider for the current state of a payment (reconciliation).
     *
     * @throws GatewayException
     */
    public function retrieve(Payment $payment): PaymentUpdate;

    /**
     * Verifies the signature of a raw webhook request and returns the decoded event.
     *
     * @param  array<string, string>  $headers  lower-cased header names
     * @return array<string, mixed>
     *
     * @throws InvalidWebhook
     */
    public function verifyWebhook(string $payload, array $headers): array;

    /**
     * Translates a verified event into provider-neutral facts.
     *
     * @param  array<string, mixed>  $event
     */
    public function normalizeEvent(array $event): GatewayEvent;

    /**
     * Refunds part or all of a paid payment.
     *
     * @throws GatewayException
     */
    public function refund(Payment $payment, Money $amount, string $idempotencyKey): RefundUpdate;
}
