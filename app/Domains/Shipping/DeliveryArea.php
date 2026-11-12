<?php

namespace App\Domains\Shipping;

use App\Core\Settings\Countries;
use App\Core\Settings\SettingsRepository;
use Illuminate\Support\Str;

/** The store's delivery boundary, applied to every shipping provider. */
final class DeliveryArea
{
    public const MODES = ['shipping_methods', 'store_country', 'store_region', 'store_city', 'selected_countries'];

    public function __construct(private readonly SettingsRepository $settings) {}

    /** @return array{mode: string, countries: list<string>} */
    public function toArray(): array
    {
        return [
            'mode' => (string) $this->settings->get('store.delivery_mode', 'shipping_methods'),
            'countries' => array_values(array_unique($this->settings->get('store.delivery_countries', []))),
        ];
    }

    /** @return list<string>|null Null leaves country coverage to shipping methods. */
    public function countries(): ?array
    {
        $config = $this->toArray();
        if ($config['mode'] === 'shipping_methods') {
            return null;
        }

        $countries = $config['mode'] === 'selected_countries'
            ? $config['countries']
            : [(string) $this->settings->get('store.country', '')];

        return array_values(array_intersect($countries, Countries::codes()));
    }

    public function allows(string $country, ?string $region = null, ?string $city = null): bool
    {
        $countries = $this->countries();
        $country = strtoupper($country);
        if ($countries !== null && ! in_array($country, $countries, true)) {
            return false;
        }

        $mode = $this->toArray()['mode'];
        $storeRegion = (string) $this->settings->get('store.region', '');
        if ($mode === 'store_region' || ($mode === 'store_city' && $storeRegion !== '')) {
            if ($storeRegion === '' || ShippingRegions::normalize($region, $country) !== ShippingRegions::normalize($storeRegion, $country)) {
                return false;
            }
        }

        if ($mode === 'store_city') {
            $storeCity = (string) $this->settings->get('store.city', '');

            return $storeCity !== '' && $this->normalizeCity($city) === $this->normalizeCity($storeCity);
        }

        return true;
    }

    public function label(): string
    {
        $mode = $this->toArray()['mode'];
        if ($mode === 'shipping_methods') {
            return 'Destinations configured in shipping methods';
        }
        if ($mode === 'selected_countries') {
            return implode(', ', array_map(Countries::name(...), $this->countries() ?? []));
        }

        $parts = [];
        if ($mode === 'store_city') {
            $parts[] = $this->settings->get('store.city');
        }
        if (in_array($mode, ['store_city', 'store_region'], true)) {
            $parts[] = $this->settings->get('store.region');
        }
        $country = $this->countries()[0] ?? null;
        $parts[] = $country !== null ? Countries::name($country) : 'Choose a store country';

        return implode(', ', array_filter($parts));
    }

    private function normalizeCity(?string $city): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($city ?? '')) ?? '');
    }
}
