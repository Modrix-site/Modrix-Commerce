<?php

namespace App\Domains\Shipping;

use App\Domains\Pricing\Money;
use App\Domains\Shipping\Contracts\ShippingRateProvider;
use App\Domains\Shipping\Models\ShippingMethod;

/**
 * Core provider: methods configured by the merchant in Admin â†’ Shipping.
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

            $regional = null;
            foreach ($method->regional_rates ?? [] as $rule) {
                if ($rule['country_code'] === strtoupper($request->countryCode)
                    && ShippingRegions::normalize($rule['region'], $request->countryCode) === ShippingRegions::normalize($request->region, $request->countryCode)) {
                    $regional = $rule;
                    break;
                }
            }
            if ($method->regions_only && $regional === null) {
                continue;
            }
            $amount = Money::of($regional['rate'] ?? $method->rate, $request->currency);
            $threshold = $regional['free_over'] ?? $method->free_over;
            $freeOver = $threshold === null ? null : Money::of($threshold, $request->currency);

            if ($freeOver !== null && ! $request->merchandiseTotal->lessThan($freeOver)) {
                $amount = Money::zero($request->currency);
            }

            $rates[] = new ShippingRate($method->code, $method->name, $amount->rounded(), implode(' · ', array_filter([$method->description, $method->delivery_estimate])) ?: null);
        }

        return $rates;
    }
}
