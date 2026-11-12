<?php

namespace App\Core\Settings;

use Closure;
use Throwable;

/**
 * Extra exchange rates modules can contribute. The storefront merges them
 * with the rates a merchant typed in by hand. A failing source is skipped.
 */
final class DisplayRateSources
{
    /** @var list<Closure(): array<string, string>> */
    private array $sources = [];

    /**
     * @param  Closure(): array<string, string>  $source  currency code => units per 1 store-currency unit
     */
    public function add(Closure $source): void
    {
        $this->sources[] = $source;
    }

    /**
     * @return array<string, string>
     */
    public function rates(): array
    {
        $rates = [];

        foreach ($this->sources as $source) {
            try {
                foreach ($source() as $code => $rate) {
                    if (is_string($code) && is_string($rate)) {
                        $rates[$code] = $rate;
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $rates;
    }
}
