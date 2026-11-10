<?php

namespace Tests\Unit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'EcoModular\\AuthorizeNet\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2).'/modules/modrixcommerce/authorize-net/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use EcoModular\AuthorizeNet\AuthorizeNetAmount;
use PHPUnit\Framework\TestCase;

class AuthorizeNetAmountTest extends TestCase
{
    public function test_amounts_are_two_decimal_strings(): void
    {
        $money = Money::of('24.5', 'USD');

        $this->assertSame('24.50', AuthorizeNetAmount::toValue($money));
        $this->assertTrue(AuthorizeNetAmount::toMoney('24.50', 'USD')->equals($money));
    }

    public function test_amounts_with_more_precision_than_authorize_net_accepts_are_refused(): void
    {
        $this->expectException(GatewayException::class);

        AuthorizeNetAmount::toValue(Money::of('10.005', 'USD'));
    }

    public function test_api_amounts_are_read_without_creating_money_from_a_float(): void
    {
        $this->assertTrue(AuthorizeNetAmount::fromApi(12.5, 'USD')->equals(Money::of('12.50', 'USD')));
        $this->assertTrue(AuthorizeNetAmount::fromApi('12.50', 'USD')->equals(Money::of('12.50', 'USD')));
        $this->assertTrue(AuthorizeNetAmount::fromApi(12, 'USD')->equals(Money::of('12.00', 'USD')));
    }
}
