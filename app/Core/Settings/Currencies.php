<?php

namespace App\Core\Settings;

/**
 * ISO 4217 codes offered as store currencies. Intentionally a curated list of
 * widely used currencies; extend as needed. Minor-unit precision is used by
 * the Money value object introduced in Phase 2.
 */
final class Currencies
{
    /** @var array<string, array{name: string, minor_units: int}> */
    public const ALL = [
        'USD' => ['name' => 'US Dollar', 'minor_units' => 2],
        'EUR' => ['name' => 'Euro', 'minor_units' => 2],
        'GBP' => ['name' => 'Pound Sterling', 'minor_units' => 2],
        'CAD' => ['name' => 'Canadian Dollar', 'minor_units' => 2],
        'AUD' => ['name' => 'Australian Dollar', 'minor_units' => 2],
        'NZD' => ['name' => 'New Zealand Dollar', 'minor_units' => 2],
        'CHF' => ['name' => 'Swiss Franc', 'minor_units' => 2],
        'SEK' => ['name' => 'Swedish Krona', 'minor_units' => 2],
        'NOK' => ['name' => 'Norwegian Krone', 'minor_units' => 2],
        'DKK' => ['name' => 'Danish Krone', 'minor_units' => 2],
        'PLN' => ['name' => 'Polish Zloty', 'minor_units' => 2],
        'MXN' => ['name' => 'Mexican Peso', 'minor_units' => 2],
        'BRL' => ['name' => 'Brazilian Real', 'minor_units' => 2],
        'PHP' => ['name' => 'Philippine Peso', 'minor_units' => 2],
        'INR' => ['name' => 'Indian Rupee', 'minor_units' => 2],
        'SGD' => ['name' => 'Singapore Dollar', 'minor_units' => 2],
        'HKD' => ['name' => 'Hong Kong Dollar', 'minor_units' => 2],
        'ZAR' => ['name' => 'South African Rand', 'minor_units' => 2],
        'AED' => ['name' => 'UAE Dirham', 'minor_units' => 2],
        'JPY' => ['name' => 'Japanese Yen', 'minor_units' => 0],
        'KRW' => ['name' => 'South Korean Won', 'minor_units' => 0],
    ];

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }
}
