<?php

namespace Tests\Unit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'EcoModular\\Tap\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2).'/modules/modrixcommerce/tap/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use EcoModular\Tap\TapAmount;
use PHPUnit\Framework\TestCase;

class TapAmountTest extends TestCase
{
    public function test_gulf_currencies_use_three_decimals_and_others_use_two(): void
    {
        $this->assertSame('10.000', TapAmount::toValue(Money::of('10', 'KWD')));
        $this->assertSame('24.50', TapAmount::toValue(Money::of('24.5', 'SAR')));
        $this->assertTrue(TapAmount::toMoney('24.50', 'SAR')->equals(Money::of('24.50', 'SAR')));
    }

    public function test_amounts_with_more_precision_than_tap_accepts_are_refused(): void
    {
        $this->expectException(GatewayException::class);

        TapAmount::toValue(Money::of('10.005', 'SAR'));
    }

    public function test_hash_amounts_are_rounded_to_the_currency_decimals(): void
    {
        $this->assertSame('1.00', TapAmount::hashValue(1, 'SAR'));
        $this->assertSame('1.000', TapAmount::hashValue(1, 'KWD'));
        $this->assertSame('2.50', TapAmount::hashValue('2.5', 'AED'));
    }
}
