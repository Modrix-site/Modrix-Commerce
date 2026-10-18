<?php

namespace Tests\Unit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'EcoModular\\Paypal\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2).'/modules/ecomodular/paypal/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use EcoModular\Paypal\PaypalAmount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaypalAmountTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function conversions(): array
    {
        return [
            'two-decimal' => ['24.50', 'USD', '24.50'],
            'zero-decimal yen' => ['1500', 'JPY', '1500'],
            'zero-decimal forint' => ['900', 'HUF', '900'],
        ];
    }

    #[DataProvider('conversions')]
    public function test_money_converts_to_paypal_decimal_strings(string $amount, string $currency, string $value): void
    {
        $money = Money::of($amount, $currency);

        $this->assertSame($value, PaypalAmount::toValue($money));
        $this->assertTrue(PaypalAmount::toMoney($value, $currency)->equals($money));
    }

    public function test_amounts_with_more_precision_than_paypal_accepts_are_refused(): void
    {
        $this->expectException(GatewayException::class);

        PaypalAmount::toValue(Money::of('10.005', 'USD'));
    }
}
