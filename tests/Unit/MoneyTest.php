<?php

namespace Tests\Unit;

use App\Domains\Pricing\Money;
use InvalidArgumentException;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_arithmetic_is_exact_without_floating_point_errors()
    {
        $total = Money::of('0.1', 'USD')->plus(Money::of('0.2', 'USD'));

        $this->assertSame('0.3000', $total->toDecimal());
        $this->assertTrue($total->equals(Money::of('0.30', 'USD')));
    }

    public function test_multiplication_and_percentage()
    {
        $line = Money::of('19.99', 'USD')->multipliedBy(3);
        $this->assertSame('59.9700', $line->toDecimal());

        $discount = Money::of('59.97', 'USD')->percentage('15');
        $this->assertSame('8.9955', $discount->toDecimal());
        $this->assertSame('9.00', $discount->rounded()->toDisplayDecimal());
    }

    public function test_rounding_respects_currency_minor_units()
    {
        $this->assertSame('10.01', Money::of('10.005', 'EUR')->rounded()->toDisplayDecimal());
        $this->assertSame('1001', Money::of('1000.5', 'JPY')->rounded()->toDisplayDecimal());
    }

    public function test_currencies_cannot_be_mixed()
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of('1', 'USD')->plus(Money::of('1', 'EUR'));
    }

    public function test_floats_are_refused()
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of(24.5, 'USD');
    }

    public function test_unsupported_currency_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of('1', 'XYZ');
    }

    public function test_comparisons_and_helpers()
    {
        $a = Money::of('5', 'USD');
        $b = Money::of('7.5', 'USD');

        $this->assertTrue($a->lessThan($b));
        $this->assertTrue($b->greaterThan($a));
        $this->assertTrue($a->minus($b)->isNegative());
        $this->assertTrue($a->minus($b)->nonNegative()->isZero());
        $this->assertTrue($a->min($b)->equals($a));
    }

    public function test_json_serialisation_exposes_display_amount_and_currency()
    {
        $json = Money::of('12.3', 'USD')->jsonSerialize();

        $this->assertSame('12.30', $json['amount']);
        $this->assertSame('USD', $json['currency']);
        $this->assertStringContainsString('12.30', $json['formatted']);
    }
}
