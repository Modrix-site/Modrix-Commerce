<?php

namespace ModrixCommerce\Razorpay;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use Brick\Math\BigDecimal;
use Throwable;

final class RazorpayAmount
{
    private static function decimals(string $currency): int
    {
        return match ($currency) {
            'JPY' => 0, 'KWD', 'BHD', 'OMR' => 3, default => 2
        };
    }

    public static function toMinor(Money $money): int
    {
        if (! in_array($money->currency(), RazorpaySettings::CURRENCIES, true)) {
            throw new GatewayException('Unsupported Razorpay currency.');
        }
        try {
            $scale = self::decimals($money->currency());
            $minor = $money->amount()->toScale($scale)->withPointMovedRight($scale)->toBigInteger()->toInt();
        } catch (Throwable) {
            throw new GatewayException('The amount cannot be represented at Razorpay’s currency precision.');
        }
        if ($scale === 3 && $minor % 10 !== 0) {
            throw new GatewayException('Razorpay requires the final minor digit to be zero for three-decimal currencies.');
        }

        return $minor;
    }

    public static function money(mixed $minor, mixed $currency): Money
    {
        if (! is_int($minor) || $minor < 0 || ! is_string($currency) || ! in_array($currency, RazorpaySettings::CURRENCIES, true)) {
            throw new GatewayException('Razorpay returned an invalid amount or currency.');
        }

        return Money::of(BigDecimal::ofUnscaledValue($minor, self::decimals($currency)), $currency);
    }
}
