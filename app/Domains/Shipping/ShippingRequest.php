<?php

namespace App\Domains\Shipping;

use App\Domains\Pricing\Money;

/**
 * What a shipping provider may consider when quoting.
 */
final class ShippingRequest
{
    public function __construct(
        public readonly string $currency,
        public readonly string $countryCode,
        public readonly ?string $region,
        public readonly ?string $postalCode,
        public readonly Money $merchandiseTotal,
        public readonly string $totalWeight,
        public readonly int $itemCount,
        public readonly ?string $city = null,
    ) {}
}
