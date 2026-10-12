<?php

namespace App\Domains\Shipping\Contracts;

use App\Domains\Shipping\ShippingRate;
use App\Domains\Shipping\ShippingRequest;

/**
 * Extension contract for shipping methods (stable public API).
 *
 * Providers return the rates available for a destination. Codes must be
 * unique across providers and stable over time because orders snapshot them.
 * Rates are computed on the server; the browser only chooses a code.
 */
interface ShippingRateProvider
{
    /**
     * @return list<ShippingRate>
     */
    public function rates(ShippingRequest $request): array;
}
