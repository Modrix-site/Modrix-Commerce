<?php

namespace App\Domains\Payments\Contracts;

use App\Domains\Checkout\Pricing\PricedCart;

/**
 * A way for a customer to pay for an order (stable public API).
 *
 * Phase 2 ships offline/manual methods only: the order is placed unpaid and
 * staff record the payment once it is received. Online gateways (Phase 3)
 * extend this with payment sessions and verified webhooks; an order is never
 * marked paid because of a browser redirect.
 */
interface PaymentMethod
{
    public function code(): string;

    public function label(): string;

    /**
     * Customer-facing instructions (shown at checkout and on the confirmation page).
     */
    public function instructions(): ?string;

    public function isAvailable(PricedCart $cart): bool;

    /**
     * True for methods such as cash on delivery, where goods ship before payment.
     */
    public function allowsFulfillmentBeforePayment(): bool;
}
