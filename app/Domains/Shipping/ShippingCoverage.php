<?php

namespace App\Domains\Shipping;

use App\Core\Settings\Countries;
use App\Domains\Shipping\Models\ShippingMethod;

/**
 * Works out which countries the store currently sells and ships to, so the
 * checkout only offers destinations it can actually fulfil.
 *
 * Coverage is the union of active methods' countries, limited by the store's
 * delivery area. A method without a country list inherits that delivery area.
 * When only one country is covered, checkout fixes the country to that value.
 */
final class ShippingCoverage
{
    /**
     * Countries the store ships to, as { code, name } options in display order.
     * Returns the full ISO list when any active method is worldwide.
     *
     * @return list<array{code: string, name: string}>
     */
    public function options(?string $currency = null): array
    {
        $codes = $this->codes($currency);

        if ($codes === null) {
            return Countries::options();
        }

        $options = [];

        foreach ($codes as $code) {
            $options[] = ['code' => $code, 'name' => Countries::name($code)];
        }

        $collator = class_exists(\Collator::class) ? new \Collator(app()->getLocale()) : null;
        usort($options, fn (array $a, array $b) => $collator
            ? (int) $collator->compare($a['name'], $b['name'])
            : strcmp($a['name'], $b['name']));

        return $options;
    }

    /**
     * The covered country codes, or null when coverage is worldwide.
     *
     * @return list<string>|null
     */
    public function codes(?string $currency = null): ?array
    {
        $query = ShippingMethod::query()->where('is_active', true);

        if ($currency !== null) {
            $query->where('currency', $currency);
        }

        $methods = $query->get();

        $allowed = app(DeliveryArea::class)->countries();

        if ($methods->isEmpty()) {
            return $allowed;
        }

        $covered = [];

        foreach ($methods as $method) {
            $countries = $method->countries;

            if ($method->regions_only) {
                $regionalCountries = array_column($method->regional_rates ?? [], 'country_code');
                $countries = empty($countries) ? $regionalCountries : array_intersect($countries, $regionalCountries);
                if ($countries === []) {
                    continue;
                }
            }

            // A method with no country list ships everywhere: coverage is worldwide.
            if ($countries === null || $countries === []) {
                return $allowed;
            }

            foreach ($countries as $code) {
                $code = strtoupper((string) $code);

                if (in_array($code, Countries::codes(), true)) {
                    $covered[$code] = true;
                }
            }
        }

        $codes = array_keys($covered);

        return $allowed === null ? $codes : array_values(array_intersect($codes, $allowed));
    }

    /**
     * The single country the store sells to, or null when more than one (or the
     * whole world) is available. Lets the checkout lock the field to that value.
     */
    public function sole(?string $currency = null): ?string
    {
        $codes = $this->codes($currency);

        return is_array($codes) && count($codes) === 1 ? $codes[0] : null;
    }
}
