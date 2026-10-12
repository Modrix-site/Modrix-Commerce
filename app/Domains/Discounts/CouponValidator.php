<?php

namespace App\Domains\Discounts;

use App\Domains\Catalog\Models\Category;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricedLine;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Discounts\Models\Coupon;
use App\Domains\Pricing\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Decides whether a coupon applies to a priced cart and which lines are
 * eligible. Every rejection carries a reason the shopper can act on.
 */
final class CouponValidator
{
    public function find(string $code): ?Coupon
    {
        return Coupon::query()->where('code', Str::upper(trim($code)))->first();
    }

    /**
     * @return array{0: string|null, 1: list<PricedLine>} [rejection reason, eligible lines]
     */
    public function check(Coupon $coupon, PricedCart $cart, PricingContext $context, ?Carbon $now = null): array
    {
        $now ??= now();

        if (! $coupon->is_active) {
            return ['This coupon is not valid.', []];
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->isAfter($now)) {
            return ['This coupon is not active yet.', []];
        }

        if ($coupon->ends_at !== null && $coupon->ends_at->isBefore($now)) {
            return ['This coupon has expired.', []];
        }

        if ($coupon->usage_limit !== null && $coupon->times_used >= $coupon->usage_limit) {
            return ['This coupon has reached its usage limit.', []];
        }

        if ($coupon->type === Coupon::TYPE_FIXED && $coupon->currency !== $cart->currency) {
            return ['This coupon cannot be used in this currency.', []];
        }

        if ($coupon->usage_limit_per_customer !== null && $this->usesByCustomer($coupon, $context) >= $coupon->usage_limit_per_customer) {
            return ['You have already used this coupon the maximum number of times.', []];
        }

        $minimum = $coupon->minSubtotal($cart->currency);

        if ($minimum !== null && $cart->subtotal()->lessThan($minimum)) {
            return ["Spend at least {$minimum->format()} to use this coupon.", []];
        }

        $eligible = $this->eligibleLines($coupon, $cart);

        if ($eligible === []) {
            return ['This coupon does not apply to the items in your cart.', []];
        }

        return [null, $eligible];
    }

    /**
     * Discount for the eligible lines, never more than their subtotal.
     *
     * @param  list<PricedLine>  $lines
     */
    public function amount(Coupon $coupon, array $lines, string $currency): Money
    {
        $base = Money::zero($currency);

        foreach ($lines as $line) {
            $base = $base->plus($line->subtotal());
        }

        $discount = $coupon->type === Coupon::TYPE_PERCENTAGE
            ? $base->percentage($coupon->value)->rounded()
            : Money::of($coupon->value, $currency)->rounded();

        return $discount->min($base)->nonNegative();
    }

    /**
     * @return list<PricedLine>
     */
    private function eligibleLines(Coupon $coupon, PricedCart $cart): array
    {
        $lines = $cart->purchasableLines();

        return match ($coupon->applies_to) {
            Coupon::APPLIES_PRODUCTS => (function () use ($coupon, $lines) {
                $ids = $coupon->products()->pluck('products.id')->all();

                return array_values(array_filter($lines, fn (PricedLine $l) => in_array($l->variant->product_id, $ids, true)));
            })(),
            Coupon::APPLIES_CATEGORIES => (function () use ($coupon, $lines) {
                $ids = $coupon->categories()->get()->flatMap(fn (Category $c) => $c->descendantIdsAndSelf())->unique()->all();

                return array_values(array_filter(
                    $lines,
                    fn (PricedLine $l) => $l->variant->product->categories->pluck('id')->intersect($ids)->isNotEmpty(),
                ));
            })(),
            default => $lines,
        };
    }

    private function usesByCustomer(Coupon $coupon, PricingContext $context): int
    {
        if ($context->customerId === null && $context->email === null) {
            return 0; // not yet identifiable; enforced again at checkout once the email is known
        }

        return $coupon->redemptions()
            ->where(function ($q) use ($context) {
                if ($context->customerId !== null) {
                    $q->orWhere('customer_id', $context->customerId);
                }

                if ($context->email !== null) {
                    $q->orWhere('email', Str::lower($context->email));
                }
            })
            ->count();
    }
}
