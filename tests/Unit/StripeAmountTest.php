<?php

namespace Tests\Unit;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Gateways\Stripe\StripeAmount;
use App\Domains\Pricing\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StripeAmountTest extends TestCase
{
    /**
     * @return array<string, array{string, string, int}>
     */
    public static function conversions(): array
    {
        return [
            'two-decimal' => ['24.50', 'USD', 2450],
            'two-decimal euro' => ['0.99', 'EUR', 99],
            'zero-decimal yen' => ['1500', 'JPY', 1500],
            'zero-decimal won' => ['12000', 'KRW', 12000],
        ];
    }

    #[DataProvider('conversions')]
    public function test_money_converts_to_stripe_minor_units_and_back(string $amount, string $currency, int $minor)
    {
        $money = Money::of($amount, $currency);

        $this->assertSame($minor, StripeAmount::toMinor($money));
        $this->assertTrue(StripeAmount::toMoney($minor, strtolower($currency))->equals($money));
    }

    public function test_amounts_with_more_precision_than_stripe_accepts_are_refused()
    {
        $this->expectException(GatewayException::class);

        StripeAmount::toMinor(Money::of('10.005', 'USD'));
    }

    public function test_fractional_zero_decimal_amounts_are_refused()
    {
        $this->expectException(GatewayException::class);

        StripeAmount::toMinor(Money::of('1500.50', 'JPY'));
    }

    public function test_decimal_places_follow_stripe_rather_than_iso()
    {
        // ISO 4217 lists no minor units for ISK and UGX, but Stripe expects two decimals.
        $this->assertSame(2, StripeAmount::decimals('ISK'));
        $this->assertSame(2, StripeAmount::decimals('UGX'));
        $this->assertSame(3, StripeAmount::decimals('KWD'));
        $this->assertSame(0, StripeAmount::decimals('jpy'));
    }
}
