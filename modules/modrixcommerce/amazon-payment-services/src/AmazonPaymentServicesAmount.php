<?php

namespace EcoModular\AmazonPaymentServices;

use App\Domains\Pricing\Money;

/**
 * Handles currency decimal conversions for Amazon Payment Services (APS).
 *
 * APS requires all amounts to be sent as integer values in the smallest unit
 * of the currency (e.g. 10.50 AED -> 1050, 10.500 KWD -> 10500).
 */
final class AmazonPaymentServicesAmount
{
    /** Currencies with 3 decimal places */
    private const THREE_DECIMALS = ['BHD', 'KWD', 'OMR', 'TND', 'JOD'];

    /** Currencies with 0 decimal places */
    private const ZERO_DECIMALS = ['JPY', 'KRW', 'VND', 'CLF', 'XAF', 'XOF', 'XPF'];

    public function decimalPlaces(string $currency): int
    {
        $currency = strtoupper(trim($currency));

        if (in_array($currency, self::THREE_DECIMALS, true)) {
            return 3;
        }

        if (in_array($currency, self::ZERO_DECIMALS, true)) {
            return 0;
        }

        return 2;
    }

    public function toMinor(Money $money): int
    {
        $decimals = $this->decimalPlaces($money->currency());
        $multiplier = 10 ** $decimals;

        return (int) round((float) $money->toDisplayDecimal() * $multiplier);
    }

    public function fromMinor(int|string $minor, string $currency): Money
    {
        $decimals = $this->decimalPlaces($currency);
        $divisor = 10 ** $decimals;
        $floatAmount = (float) $minor / $divisor;

        return Money::of(number_format($floatAmount, $decimals, '.', ''), $currency);
    }
}
