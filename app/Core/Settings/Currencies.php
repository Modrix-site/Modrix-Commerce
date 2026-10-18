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
        'SAR' => ['name' => 'Saudi Riyal', 'minor_units' => 2],
        'JPY' => ['name' => 'Japanese Yen', 'minor_units' => 0],
        'KRW' => ['name' => 'South Korean Won', 'minor_units' => 0],
        'BHD' => ['name' => 'Bahraini Dinar', 'minor_units' => 3],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'minor_units' => 3],
        'OMR' => ['name' => 'Omani Rial', 'minor_units' => 3],
        'QAR' => ['name' => 'Qatari Riyal', 'minor_units' => 2],
        'EGP' => ['name' => 'Egyptian Pound', 'minor_units' => 2],
        'JOD' => ['name' => 'Jordanian Dinar', 'minor_units' => 3],
        'TRY' => ['name' => 'Turkish Lira', 'minor_units' => 2],
        'CNY' => ['name' => 'Chinese Yuan', 'minor_units' => 2],
        'PKR' => ['name' => 'Pakistani Rupee', 'minor_units' => 2],
        'BDT' => ['name' => 'Bangladeshi Taka', 'minor_units' => 2],
        'LKR' => ['name' => 'Sri Lankan Rupee', 'minor_units' => 2],
        'NPR' => ['name' => 'Nepalese Rupee', 'minor_units' => 2],
        'IDR' => ['name' => 'Indonesian Rupiah', 'minor_units' => 2],
        'MYR' => ['name' => 'Malaysian Ringgit', 'minor_units' => 2],
        'THB' => ['name' => 'Thai Baht', 'minor_units' => 2],
        'VND' => ['name' => 'Vietnamese Dong', 'minor_units' => 0],
        'NGN' => ['name' => 'Nigerian Naira', 'minor_units' => 2],
        'KES' => ['name' => 'Kenyan Shilling', 'minor_units' => 2],
        'MAD' => ['name' => 'Moroccan Dirham', 'minor_units' => 2],
        'TND' => ['name' => 'Tunisian Dinar', 'minor_units' => 3],
        'ILS' => ['name' => 'Israeli New Shekel', 'minor_units' => 2],
        'UAH' => ['name' => 'Ukrainian Hryvnia', 'minor_units' => 2],
        'CZK' => ['name' => 'Czech Koruna', 'minor_units' => 2],
        'HUF' => ['name' => 'Hungarian Forint', 'minor_units' => 2],
        'RON' => ['name' => 'Romanian Leu', 'minor_units' => 2],
    ];

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }
}
