<?php

namespace Tests\Unit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'EcoModular\\CheckoutCom\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2).'/modules/ecomodular/checkout-com/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use EcoModular\CheckoutCom\CheckoutComAmount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CheckoutComAmountTest extends TestCase
{
    /**
     * @return array<string, array{string, string, int}>
     */
    public static function conversions(): array
    {
        return [
            'two-decimal' => ['24.50', 'USD', 2450],
            'zero-decimal yen' => ['1500', 'JPY', 1500],
            'three-decimal dinar' => ['1.234', 'BHD', 1234],
        ];
    }

    #[DataProvider('conversions')]
    public function test_money_converts_to_checkout_com_minor_units_and_back(string $amount, string $currency, int $minor): void
    {
        $money = Money::of($amount, $currency);

        $this->assertSame($minor, CheckoutComAmount::toMinor($money));
        $this->assertTrue(CheckoutComAmount::toMoney($minor, $currency)->equals($money));
    }

    public function test_amounts_with_more_precision_than_checkout_com_accepts_are_refused(): void
    {
        $this->expectException(GatewayException::class);

        CheckoutComAmount::toMinor(Money::of('10.005', 'USD'));
    }
}
