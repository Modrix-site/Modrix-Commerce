<?php

namespace App\Domains\Shipping;

use App\Core\Settings\StorefrontCurrencies;
use App\Core\Settings\StoreSettings;
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
        if (! app(DeliveryArea::class)->allows($request->countryCode, $request->region, $request->city)) {
            return [];
        }

        $rates = [];

        $methods = ShippingMethod::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $matched = $methods->where('currency', $request->currency)->values();
        if ($matched->isEmpty()) {
            $matched = $methods->where('currency', app(StoreSettings::class)->currency())->values();
        }

        foreach ($matched as $method) {
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
            $amount = Money::of($regional['rate'] ?? $method->rate, $method->currency);
            $threshold = $regional['free_over'] ?? $method->free_over;
            $freeOver = $threshold === null ? null : Money::of($threshold, $method->currency);

            if ($method->currency !== $request->currency) {
                $amount = app(StorefrontCurrencies::class)->convert($amount, $request->currency);
                $freeOver = $freeOver === null ? null : app(StorefrontCurrencies::class)->convert($freeOver, $request->currency);
                if ($amount === null || ($threshold !== null && $freeOver === null)) {
                    continue;
                }
            }

            if ($freeOver !== null && ! $request->merchandiseTotal->lessThan($freeOver)) {
                $amount = Money::zero($request->currency);
            }

            $rates[] = new ShippingRate($method->code, $method->name, $amount->rounded(), implode(' · ', array_filter([$method->description, $method->delivery_estimate])) ?: null);
        }

        return $rates;
    }
}
