<?php

namespace App\Domains\Tax;

use App\Domains\Tax\Contracts\TaxRateProvider;
use App\Domains\Tax\Models\TaxRateRow;
use Illuminate\Support\Str;

/**
 * Core provider: the most specific active row wins (country + region over country).
 */
final class TableTaxRateProvider implements TaxRateProvider
{
    public function rateFor(string $countryCode, ?string $region, ?string $postalCode): ?TaxRate
    {
        $rows = TaxRateRow::query()
            ->where('is_active', true)
            ->where('country_code', strtoupper($countryCode))
            ->get();

        $regionKey = $region === null ? null : Str::lower(trim($region));

        $match = $rows->first(fn (TaxRateRow $r) => $r->region !== null && $regionKey !== null && Str::lower($r->region) === $regionKey)
            ?? $rows->first(fn (TaxRateRow $r) => $r->region === null);

        return $match === null ? null : new TaxRate($match->name, $match->rate);
    }
}
