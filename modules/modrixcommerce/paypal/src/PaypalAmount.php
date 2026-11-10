<?php

namespace EcoModular\Paypal;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use Brick\Math\Exception\RoundingNecessaryException;

/**
 * PayPal amounts are decimal strings, not minor units. JPY, HUF and TWD have
 * no fractional digits.
 *
 * @see https://developer.paypal.com/api/rest/reference/currency-codes/
 */
final class PaypalAmount
{
    private const ZERO_DECIMAL = ['HUF', 'JPY', 'TWD'];

    public static function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    /**
     * @throws GatewayException
     */
    public static function toValue(Money $money): string
    {
        try {
            return (string) $money->amount()->toScale(self::decimals($money->currency()));
        } catch (RoundingNecessaryException) {
            throw new GatewayException("The amount {$money->toDecimal()} {$money->currency()} has more precision than PayPal accepts.");
        }
    }

    public static function toMoney(string $value, string $currency): Money
    {
        return Money::of($value, strtoupper($currency));
    }
}
