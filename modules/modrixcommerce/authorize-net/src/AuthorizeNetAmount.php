<?php

namespace EcoModular\AuthorizeNet;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use Brick\Math\Exception\RoundingNecessaryException;

/**
 * Authorize.Net transaction amounts are decimal strings with two places.
 *
 * @see https://developer.authorize.net/api/reference/features/accept-hosted.html
 */
final class AuthorizeNetAmount
{
    /**
     * @throws GatewayException
     */
    public static function toValue(Money $money): string
    {
        try {
            return (string) $money->amount()->toScale(2);
        } catch (RoundingNecessaryException) {
            throw new GatewayException("The amount {$money->toDecimal()} {$money->currency()} has more precision than Authorize.Net accepts.");
        }
    }

    public static function toMoney(string $value, string $currency): Money
    {
        return Money::of($value, strtoupper($currency));
    }

    /**
     * JSON numbers arrive as ints or floats. Money refuses floats, so a float
     * is formatted to the two places Authorize.Net uses before it is parsed.
     *
     * @throws GatewayException
     */
    public static function fromApi(mixed $value, string $currency): Money
    {
        if (is_int($value)) {
            return self::toMoney($value.'.00', $currency);
        }

        if (is_string($value) && preg_match('/^\d+(\.\d+)?$/', $value) === 1) {
            return self::toMoney($value, $currency);
        }

        if (is_float($value) && is_finite($value) && $value >= 0) {
            return self::toMoney(sprintf('%.2f', $value), $currency);
        }

        throw new GatewayException('Authorize.Net returned an unreadable amount.');
    }
}
