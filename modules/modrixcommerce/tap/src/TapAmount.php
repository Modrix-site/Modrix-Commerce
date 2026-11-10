<?php

namespace EcoModular\Tap;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;

/**
 * Tap charge amounts are decimal strings. Bahraini, Kuwaiti and Omani dinars
 * and the Jordanian dinar use three decimal places. Every other Tap currency
 * uses two.
 *
 * @see https://developers.tap.company/docs/webhook
 */
final class TapAmount
{
    private const THREE_DECIMAL = ['BHD', 'KWD', 'OMR', 'JOD'];

    public static function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), self::THREE_DECIMAL, true) ? 3 : 2;
    }

    /**
     * @throws GatewayException
     */
    public static function toValue(Money $money): string
    {
        try {
            return (string) $money->amount()->toScale(self::decimals($money->currency()));
        } catch (RoundingNecessaryException) {
            throw new GatewayException("The amount {$money->toDecimal()} {$money->currency()} has more precision than Tap accepts.");
        }
    }

    public static function toMoney(string $value, string $currency): Money
    {
        return Money::of($value, strtoupper($currency));
    }

    /**
     * The amount string Tap expects inside a webhook hashstring.
     *
     * @throws GatewayException
     */
    public static function hashValue(mixed $amount, string $currency): string
    {
        if (is_int($amount)) {
            $decimal = BigDecimal::of($amount);
        } elseif (is_float($amount)) {
            $decimal = BigDecimal::of((string) $amount);
        } elseif (is_string($amount) && preg_match('/^-?\d+(\.\d+)?$/', $amount) === 1) {
            $decimal = BigDecimal::of($amount);
        } else {
            throw new GatewayException('Tap returned an amount this store cannot read.');
        }

        try {
            return (string) $decimal->toScale(self::decimals($currency), RoundingMode::HALF_UP);
        } catch (MathException) {
            throw new GatewayException('Tap returned an amount this store cannot read.');
        }
    }
}
