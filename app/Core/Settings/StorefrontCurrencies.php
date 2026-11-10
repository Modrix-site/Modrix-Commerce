<?php

namespace App\Core\Settings;

use App\Domains\Pricing\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class StorefrontCurrencies
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly StoreSettings $store,
        private readonly DisplayRateSources $sources,
    ) {}

    /** @return array<string,string> */
    public function rates(): array
    {
        $rates = [$this->store->currency() => '1'];
        $selected = [];
        foreach ((array) $this->settings->get('store.display_currencies', []) as $entry) {
            if (is_array($entry) && is_string($entry['code'] ?? null) && is_string($entry['rate'] ?? null)) {
                $this->accept($rates, $entry['code'], $entry['rate']);
                $selected[$entry['code']] = true;
            }
        }
        foreach ($this->sources->rates() as $code => $rate) {
            if (isset($selected[$code])) {
                $this->accept($rates, $code, $rate);
            }
        }
        $rates[$this->store->currency()] = '1';

        return $rates;
    }

    /**
     * @param  array<string, string>  $rates
     */
    private function accept(array &$rates, string $code, string $rate): void
    {
        if (isset(Currencies::ALL[$code]) && preg_match('/^\d{1,9}(\.\d{1,8})?$/', $rate) && BigDecimal::of($rate)->isPositive()) {
            $rates[$code] = $rate;
        }
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

    /**
     * Converts a charge into another currency. Display rates win, then any
     * rate a module published, then a cached public rate for the store currency.
     * Returns null when no rate exists, so a missing rate is never treated as 1:1.
     */
    public function convert(Money $money, string $target): ?Money
    {
        $target = strtoupper($target);
        if ($money->currency() === $target) {
            return $money;
        }

        $rates = $this->chargeRates($money->currency(), $target);
        if (! isset($rates[$target], $rates[$money->currency()])) {
            return null;
        }

        $amount = $money->amount()->dividedBy($rates[$money->currency()], 12, RoundingMode::HALF_UP)->multipliedBy($rates[$target]);

        return Money::of($amount, $target)->rounded();
    }

    /**
     * @return array<string, string>
     */
    private function chargeRates(string $source, string $target): array
    {
        $rates = $this->rates();
        foreach ($this->sources->rates() as $code => $rate) {
            $this->accept($rates, (string) $code, (string) $rate);
        }
        $base = $this->store->currency();
        $rates[$base] = '1';

        if (isset($rates[$source], $rates[$target]) || app()->runningUnitTests()) {
            return $rates;
        }

        foreach ($this->liveRates($base) as $code => $rate) {
            if (! isset($rates[$code])) {
                $this->accept($rates, $code, $rate);
            }
        }
        $rates[$base] = '1';

        return $rates;
    }

    /**
     * Units of each currency bought by one unit of the store currency.
     *
     * @return array<string, string>
     */
    private function liveRates(string $base): array
    {
        if (! preg_match('/^[A-Z]{3}$/', $base)) {
            return [];
        }

        $key = 'storefront.exchange-rates';
        $cached = Cache::get($key);
        if (is_array($cached) && ($cached['base'] ?? null) === $base && is_array($cached['rates'] ?? null)) {
            return $cached['rates'];
        }

        $rates = [];
        try {
            $response = Http::timeout(5)->acceptJson()->get('https://open.er-api.com/v6/latest/'.$base);
            $body = $response->json();
            if ($response->ok() && is_array($body) && ($body['result'] ?? null) === 'success' && is_array($body['rates'] ?? null)) {
                foreach ($body['rates'] as $code => $rate) {
                    $code = strtoupper((string) $code);
                    if (! is_int($rate) && ! is_float($rate) && ! (is_string($rate) && is_numeric($rate))) {
                        continue;
                    }
                    $plain = rtrim(rtrim(sprintf('%.8F', (float) $rate), '0'), '.');
                    if ($code !== $base) {
                        $this->accept($rates, $code, $plain);
                    }
                }
            }
        } catch (Throwable) {
            $rates = [];
        }

        Cache::put($key, ['base' => $base, 'rates' => $rates], now()->addHours($rates === [] ? 0 : 6)->addMinutes($rates === [] ? 10 : 0));

        return $rates;
    }
}
