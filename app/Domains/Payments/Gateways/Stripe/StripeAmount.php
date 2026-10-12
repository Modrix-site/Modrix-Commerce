<?php

namespace App\Domains\Payments\Gateways\Stripe;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;

/**
 * Converts between Money and Stripe's integer amounts.
 *
 * Stripe's decimal places differ from ISO 4217 for a few currencies (e.g. ISK
 * and UGX are sent with two decimals), so this follows Stripe's own lists
 * rather than the store's currency table.
 *
 * @see https://docs.stripe.com/currencies#zero-decimal
 */
final class StripeAmount
{
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    private const THREE_DECIMAL = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

    /** Conservative minimum charge in minor units (Stripe's minimums are around 0.50 USD). */
    public const MINIMUM = 50;

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
     * @throws GatewayException when the amount has more precision than Stripe accepts
     */
    public static function toMinor(Money $money): int
    {
        $decimals = self::decimals($money->currency());

        try {
            $minor = $money->amount()->toScale($decimals)->withPointMovedRight($decimals)->toBigInteger()->toInt();
        } catch (RoundingNecessaryException) {
            throw new GatewayException("The amount {$money->toDecimal()} {$money->currency()} cannot be charged with Stripe's precision.");
        }

        // Stripe requires three-decimal amounts to be multiples of 10.
        if ($decimals === 3 && $minor % 10 !== 0) {
            throw new GatewayException("Stripe requires {$money->currency()} amounts rounded to two decimals.");
        }

        return $minor;
    }

    public static function toMoney(int $minor, string $currency): Money
    {
        return Money::of(BigDecimal::ofUnscaledValue($minor, self::decimals($currency)), strtoupper($currency));
    }
}
