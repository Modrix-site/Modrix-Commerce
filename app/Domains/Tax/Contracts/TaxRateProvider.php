<?php

namespace App\Domains\Tax\Contracts;

use App\Domains\Tax\TaxRate;

/**
 * Extension contract for tax calculation (stable public API).
 *
 * Returns the rate that applies to a destination, or null for no tax.
 * EcoModular does not claim tax compliance for any jurisdiction: merchants
 * must configure rates (or install a provider) suitable for where they sell.
 */
interface TaxRateProvider
{
    public function rateFor(string $countryCode, ?string $region, ?string $postalCode): ?TaxRate;
}
