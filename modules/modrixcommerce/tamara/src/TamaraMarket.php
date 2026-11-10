<?php

namespace EcoModular\Tamara;

/**
 * Tamara markets. The market follows the store currency (one currency per
 * store, ADR-014): Tamara operates across the GCC, so any supported currency
 * is served. The API host depends on the merchant's environment, not on the
 * country; production and sandbox have their own hosts.
 */
final class TamaraMarket
{
    public const PRODUCTION = 'production';

    public const SANDBOX = 'sandbox';

    public const MARKETS = [
        'AED' => ['name' => 'United Arab Emirates', 'country' => 'AE'],
        'SAR' => ['name' => 'Saudi Arabia', 'country' => 'SA'],
        'KWD' => ['name' => 'Kuwait', 'country' => 'KW'],
        'BHD' => ['name' => 'Bahrain', 'country' => 'BH'],
        'OMR' => ['name' => 'Oman', 'country' => 'OM'],
        'QAR' => ['name' => 'Qatar', 'country' => 'QA'],
    ];

    private function __construct(public readonly string $currency) {}

    public static function for(string $currency): ?self
    {
        $currency = strtoupper($currency);

        return isset(self::MARKETS[$currency]) ? new self($currency) : null;
    }

    public function name(): string
    {
        return self::MARKETS[$this->currency]['name'];
    }

    public function country(): string
    {
        return self::MARKETS[$this->currency]['country'];
    }

    public function apiBase(string $environment): string
    {
        $default = $environment === self::SANDBOX
            ? 'https://api-sandbox.tamara.co'
            : 'https://api.tamara.co';

        $override = config('services.tamara.base_url.'.$environment);

        return is_string($override) && $override !== '' ? $override : $default;
    }

    public function dashboard(): string
    {
        return 'https://merchants.tamara.co';
    }
}
