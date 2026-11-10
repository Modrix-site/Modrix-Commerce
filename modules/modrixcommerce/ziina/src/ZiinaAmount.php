<?php

namespace EcoModular\Ziina;

use App\Domains\Pricing\Money;

/**
 * Handles minor unit formatting and parsing for Ziina (e.g. 100.00 AED -> 10000 fils).
 */
final class ZiinaAmount
{
    public static function toMinorUnits(Money $money): int
    {
        return (int) round((float) $money->toDisplayDecimal() * 100);
    }

    public static function fromMinorUnits(int|float $value, string $currency): Money
    {
        $decimal = number_format(((float) $value) / 100, 2, '.', '');

        return Money::of($decimal, strtoupper($currency));
    }
}
