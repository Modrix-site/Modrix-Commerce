<?php

namespace EcoModular\CheckoutCom;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;

/**
 * Checkout.com amounts are integers in the currency's minor unit.
 *
 * @see https://www.checkout.com/docs/payments/accept-payments/format-the-amount-value
 */
final class CheckoutComAmount
{
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    private const THREE_DECIMAL = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];

    public static function decimals(string $currency): int
    {
        $currency = strtoupper($currency);

        return match (true) {
            in_array($currency, self::ZERO_DECIMAL, true) => 0,
            in_array($currency, self::THREE_DECIMAL, true) => 3,
            default => 2,
        };
    }

    /**
     * @throws GatewayException
     */
    public static function toMinor(Money $money): int
    {
        $decimals = self::decimals($money->currency());

        try {
            return $money->amount()->toScale($decimals)->withPointMovedRight($decimals)->toBigInteger()->toInt();
        } catch (RoundingNecessaryException) {
            throw new GatewayException("The amount {$money->toDecimal()} {$money->currency()} has more precision than Checkout.com accepts.");
        }
    }

    public static function toMoney(int $minor, string $currency): Money
    {
        return Money::of(BigDecimal::ofUnscaledValue($minor, self::decimals($currency)), strtoupper($currency));
    }
}
