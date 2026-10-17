<?php

namespace App\Domains\Checkout;

use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Pricing\Money;
use App\Domains\Shipping\Models\ShippingMethod;

/**
 * How far a cart is from free shipping: the lowest "free over" amount of
 * the active shipping methods (in the cart's currency) that ship to the
 * country, or to any country when it is not known yet.
 */
final class FreeShippingProgress
{
    /**
     * @return array{threshold: Money, remaining: Money}|null null when no method offers free shipping
     */
    public static function for(PricedCart $cart, ?string $country = null): ?array
    {
        $thresholds = ShippingMethod::query()
            ->where('is_active', true)
            ->where('currency', $cart->currency)
            ->whereNotNull('free_over')
            ->get()
            ->filter(fn (ShippingMethod $method) => $country === null || $method->shipsTo($country))
            ->map(fn (ShippingMethod $method) => Money::of((string) $method->free_over, $cart->currency));

        if ($thresholds->isEmpty()) {
            return null;
        }

        $threshold = $thresholds->reduce(fn (?Money $lowest, Money $amount) => $lowest === null || $amount->lessThan($lowest) ? $amount : $lowest);
        // Same basis as the shipping step: merchandise after discounts.
        $remaining = $threshold->minus($cart->subtotal()->minus($cart->discountTotal));

        return [
            'threshold' => $threshold,
            'remaining' => $remaining->isNegative() ? Money::zero($cart->currency) : $remaining,
        ];
    }
}
