<?php

namespace EcoModular\NGenius;

use App\Domains\Pricing\Money;

/**
 * Handles minor unit formatting and parsing for Network International (N-Genius).
 *
 * Most currencies (AED, SAR, USD, EUR) use 2 decimal places (value * 100).
 * Currencies such as KWD, BHD, OMR, and JOD use 3 decimal places (value * 1000).
 * Currencies such as JPY use 0 decimal places (value * 1).
 */
final class NGeniusAmount
{
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    private const THREE_DECIMAL_CURRENCIES = [
        'BHD', 'IQD', 'JOD', 'KWD', 'OMR', 'TND',
    ];

    public static function decimalPlaces(string $currency): int
    {
        $code = strtoupper($currency);

        if (in_array($code, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return 0;
        }

        if (in_array($code, self::THREE_DECIMAL_CURRENCIES, true)) {
            return 3;
        }

        return 2;
    }

    public static function toMinorUnits(Money $money): int
    {
        $decimals = self::decimalPlaces($money->currency());
        $multiplier = 10 ** $decimals;

        return (int) round((float) $money->toDisplayDecimal() * $multiplier);
    }

    public static function fromMinorUnits(int $value, string $currency): Money
    {
        $decimals = self::decimalPlaces($currency);
        $divisor = 10 ** $decimals;

        $floatVal = (float) $value / $divisor;
        $decimalString = number_format($floatVal, $decimals, '.', '');

        return Money::of($decimalString, strtoupper($currency));
    }
}
