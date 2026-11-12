<?php

namespace App\Domains\Checkout\Pricing\Steps;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\PricingStep;
use App\Domains\Pricing\Money;
use App\Domains\Tax\Contracts\TaxRateProvider;
use App\Domains\Tax\TaxSettings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Calculates tax per line (after discounts) and on shipping when configured.
 *
 * Prices exclusive of tax: tax = taxable × rate.
 * Prices inclusive of tax: tax = taxable − taxable ÷ (1 + rate), already in the price.
 * Each line is rounded to minor units, then summed.
 */
final class TaxStep implements PricingStep
{
    public function __construct(
        private readonly TaxRateProvider $provider,
        private readonly TaxSettings $settings,
    ) {}

    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void
    {
        $priced->pricesIncludeTax = $this->settings->pricesIncludeTax();

        if ($context->countryCode === null) {
            return;
        }

        $rate = $this->provider->rateFor($context->countryCode, $context->region, $context->postalCode);

        if ($rate === null || BigDecimal::of($rate->percent)->isZero()) {
            return;
        }

        $total = Money::zero($priced->currency);

        foreach ($priced->purchasableLines() as $line) {
            $line->taxRate = $rate->percent;
            $line->tax = $this->taxOn($line->subtotal()->minus($line->discount)->nonNegative(), $rate->percent, $priced->pricesIncludeTax);
            $total = $total->plus($line->tax);
        }

        if ($this->settings->taxShipping() && $priced->shippingTotal->isPositive()) {
            $priced->shippingTax = $this->taxOn($priced->shippingTotal, $rate->percent, $priced->pricesIncludeTax);
            $total = $total->plus($priced->shippingTax);
        }

        $priced->taxTotal = $total;
    }

    private function taxOn(Money $taxable, string $percent, bool $inclusive): Money
    {
        if (! $inclusive) {
            return $taxable->percentage($percent)->rounded();
        }

        $divisor = BigDecimal::one()->plus(BigDecimal::of($percent)->dividedBy(100, 6, RoundingMode::HALF_UP));
        $net = $taxable->amount()->dividedBy($divisor, 4, RoundingMode::HALF_UP);

        return Money::of($taxable->amount()->minus($net), $taxable->currency())->rounded();
    }
}
