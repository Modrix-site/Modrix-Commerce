<?php

namespace App\Core\Settings;

/**
 * Typed accessors for store-wide settings with sane defaults.
 */
final class StoreSettings
{
    public const UNITS_WEIGHT = ['kg', 'g', 'lb', 'oz'];

    public const UNITS_DIMENSION = ['cm', 'mm', 'm', 'in'];

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return array{store_name: string, store_email: string|null, default_currency: string, timezone: string, weight_unit: string, dimension_unit: string}
     */
    public function toArray(): array
    {
        return [
            'store_name' => (string) $this->settings->get('store.name', config('app.name')),
            'store_email' => $this->settings->get('store.email'),
            'default_currency' => (string) $this->settings->get('store.default_currency', 'USD'),
            'timezone' => (string) $this->settings->get('store.timezone', config('app.timezone')),
            'weight_unit' => (string) $this->settings->get('store.weight_unit', 'kg'),
            'dimension_unit' => (string) $this->settings->get('store.dimension_unit', 'cm'),
        ];
    }

    /**
     * @param  array{store_name: string, store_email?: string|null, default_currency: string, timezone: string, weight_unit?: string, dimension_unit?: string}  $values
     */
    public function update(array $values): void
    {
        $this->settings->setMany(array_filter([
            'store.name' => $values['store_name'],
            'store.email' => $values['store_email'] ?? null,
            'store.default_currency' => strtoupper($values['default_currency']),
            'store.timezone' => $values['timezone'],
            'store.weight_unit' => $values['weight_unit'] ?? null,
            'store.dimension_unit' => $values['dimension_unit'] ?? null,
        ], fn ($v, $k) => $v !== null || $k === 'store.email', ARRAY_FILTER_USE_BOTH));
    }

    public function name(): string
    {
        return $this->toArray()['store_name'];
    }

    public function currency(): string
    {
        return $this->toArray()['default_currency'];
    }

    public function timezone(): string
    {
        return $this->toArray()['timezone'];
    }
}
