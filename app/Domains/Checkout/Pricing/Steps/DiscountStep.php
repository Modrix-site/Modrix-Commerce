<?php

namespace App\Domains\Checkout\Pricing\Steps;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricedLine;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\PricingStep;
use App\Domains\Discounts\CouponValidator;
use App\Domains\Pricing\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Applies the cart's coupon and spreads the discount over eligible lines
 * (so per-line tax and order item snapshots stay consistent).
 */
final class DiscountStep implements PricingStep
{
    public function __construct(private readonly CouponValidator $coupons) {}

    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void
    {
        if ($priced->couponCode === null) {
            return;
        }

        $coupon = $this->coupons->find($priced->couponCode);

        if ($coupon === null) {
            $priced->couponError = 'This coupon code is not valid.';

            return;
        }

        [$reason, $eligible] = $this->coupons->check($coupon, $priced, $context);

        if ($reason !== null) {
            $priced->couponError = $reason;

            return;
        }

        $discount = $this->coupons->amount($coupon, $eligible, $priced->currency);
        $this->allocate($discount, $eligible);
        $priced->discountTotal = $discount;
    }

    /**
     * Proportional split in minor units; the last line absorbs rounding so the
     * parts always add up to the total exactly.
     *
     * @param  list<PricedLine>  $lines
     */
    private function allocate(Money $discount, array $lines): void
    {
        $base = BigDecimal::zero();

        foreach ($lines as $line) {
            $base = $base->plus($line->subtotal()->amount());
        }

        if ($base->isZero()) {
            return;
        }

        $remaining = $discount;
        $last = count($lines) - 1;

        foreach ($lines as $i => $line) {
            if ($i === $last) {
                $line->discount = $remaining;
                break;
            }

            $share = Money::of(
                $discount->amount()->multipliedBy($line->subtotal()->amount())->dividedBy($base, 4, RoundingMode::DOWN),
                $discount->currency(),
            )->rounded();

            $line->discount = $share;
            $remaining = $remaining->minus($share);
        }
    }
}
