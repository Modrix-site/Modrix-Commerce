<?php

namespace App\Domains\Checkout\Pricing;

use App\Domains\Cart\Models\Cart;

/**
 * A stage of cart pricing (discounts, shipping, tax). Steps run in the order
 * they are registered and may only use server-side data.
 */
interface PricingStep
{
    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void;
}
