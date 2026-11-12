<?php

namespace App\Domains\Checkout\Contracts;

use App\Domains\Checkout\CheckoutData;
use App\Domains\Checkout\Pricing\PricedCart;

/**
 * A rule that can refuse an order before it is placed (stable public API),
 * e.g. age verification, delivery-area or quantity rules.
 *
 * Runs inside the order transaction after server-side repricing, before
 * stock is allocated. Validators must not have side effects. A validator
 * that throws blocks checkout (fail closed) and is logged.
 */
interface CheckoutValidator
{
    /**
     * @return string|null a customer-facing reason to refuse, or null to allow
     */
    public function validate(PricedCart $cart, CheckoutData $data): ?string;
}
