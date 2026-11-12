<?php

namespace App\Domains\Shipping;

use App\Domains\Pricing\Money;
use App\Domains\Shipping\Models\ShippingMethod;

final class ShippingLocationAudit
{
    /** @param iterable<ShippingMethod> $methods
     * @return list<string>
     */
    public function warnings(iterable $methods): array
    {
        $warnings = [];
        $seen = [];
        foreach ($methods as $method) {
            if (! $method->is_active) {
                continue;
            }
            $countries = array_values(array_unique($method->countries ?? []));
            sort($countries);
            if (count($countries) !== count($method->countries ?? [])) {
                $warnings[] = "{$method->name} has duplicate country entries. Edit and save its coverage to remove them.";
            }
            $regions = [];
            foreach ($method->regional_rates ?? [] as $rule) {
                $location = $rule['country_code'].':'.ShippingRegions::normalize($rule['region'], $rule['country_code']);
                if (isset($regions[$location])) {
                    $warnings[] = "{$method->name} has duplicate rates for {$rule['region']}, {$rule['country_code']}. Keep one rate for this location.";
                }
                $regions[$location] = [Money::of($rule['rate'], $method->currency)->toDecimal(), $rule['free_over'] ?? $method->free_over];
            }
            ksort($regions);
            $fingerprint = json_encode([$method->currency, $countries, $method->regions_only, $regions, $method->rate, $method->free_over], JSON_THROW_ON_ERROR);
            if (isset($seen[$fingerprint])) {
                $warnings[] = "{$seen[$fingerprint]} and {$method->name} have the same delivery locations and rates. Remove a duplicate, or keep both if they are separate delivery services.";
            } else {
                $seen[$fingerprint] = $method->name;
            }
        }

        return array_values(array_unique($warnings));
    }
}
