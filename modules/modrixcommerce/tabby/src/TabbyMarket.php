<?php

namespace EcoModular\Tabby;

/**
 * Tabby markets. The market follows the store currency (one currency per
 * store, ADR-014): AED is served from the UAE platform, SAR from KSA.
 */
final class TabbyMarket
{
    public const MARKETS = [
        'AED' => ['name' => 'United Arab Emirates', 'api' => 'https://api.tabby.ai', 'checkout' => 'https://checkout.tabby.ai', 'dashboard' => 'https://merchant.tabby.ai'],
        'SAR' => ['name' => 'Saudi Arabia', 'api' => 'https://api.tabby.sa', 'checkout' => 'https://checkout.tabby.sa', 'dashboard' => 'https://merchant.tabby.sa'],
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

    public function apiBase(): string
    {
        return (string) config('services.tabby.base_url.'.$this->currency, self::MARKETS[$this->currency]['api']);
    }

    public function checkoutHost(): string
    {
        return self::MARKETS[$this->currency]['checkout'];
    }

    public function dashboard(): string
    {
        return self::MARKETS[$this->currency]['dashboard'];
    }
}
