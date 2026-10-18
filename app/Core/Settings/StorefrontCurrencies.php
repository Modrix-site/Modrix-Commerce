<?php

namespace App\Core\Settings;

use App\Domains\Pricing\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class StorefrontCurrencies
{
    public function __construct(private readonly SettingsRepository $settings, private readonly StoreSettings $store) {}

    /** @return array<string,string> */
    public function rates(): array
    {
        $rates = [$this->store->currency() => '1'];
        foreach ((array) $this->settings->get('store.display_currencies', []) as $entry) {
            if (is_array($entry) && is_string($entry['code'] ?? null) && isset(Currencies::ALL[$entry['code']]) && is_string($entry['rate'] ?? null) && preg_match('/^\d{1,9}(\.\d{1,8})?$/', $entry['rate']) && BigDecimal::of($entry['rate'])->isPositive()) {
                $rates[$entry['code']] = $entry['rate'];
            }
        }
        $rates[$this->store->currency()] = '1';

        return $rates;
    }

    public function display(Money $money, string $target): Money
    {
        $rates = $this->rates();
        if (! isset($rates[$target], $rates[$money->currency()])) {
            return $money;
        }
        $amount = $money->amount()->dividedBy($rates[$money->currency()], 12, RoundingMode::HALF_UP)->multipliedBy($rates[$target]);

        return Money::of($amount, $target)->rounded();
    }
}
