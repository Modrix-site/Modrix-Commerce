<?php

namespace App\Domains\Shipping;

use App\Domains\Pricing\Money;
use App\Domains\Shipping\Contracts\ShippingRateProvider;
use App\Domains\Shipping\Models\ShippingMethod;

/**
 * Core provider: methods configured by the merchant in Admin → Shipping.
 */
final class TableShippingRateProvider implements ShippingRateProvider
{
    public function rates(ShippingRequest $request): array
    {
        $rates = [];

        $methods = ShippingMethod::query()
            ->where('is_active', true)
            ->where('currency', $request->currency)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($methods as $method) {
            if (! $method->shipsTo($request->countryCode)) {
                continue;
            }

            $amount = Money::of($method->rate, $request->currency);
            $freeOver = $method->free_over === null ? null : Money::of($method->free_over, $request->currency);

            if ($freeOver !== null && ! $request->merchandiseTotal->lessThan($freeOver)) {
                $amount = Money::zero($request->currency);
            }

            $rates[] = new ShippingRate($method->code, $method->name, $amount->rounded(), $method->description);
        }

        return $rates;
    }
}
