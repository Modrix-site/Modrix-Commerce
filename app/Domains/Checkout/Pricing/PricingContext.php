<?php

namespace App\Domains\Checkout\Pricing;

/**
 * Inputs that influence price besides the cart lines. Contains only
 * identifiers and the destination: never client-supplied amounts.
 */
final class PricingContext
{
    public function __construct(
        public readonly ?int $customerId = null,
        public readonly ?string $email = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $region = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $shippingMethod = null,
    ) {}
}
