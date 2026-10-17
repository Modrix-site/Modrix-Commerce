<?php

namespace App\Domains\Checkout;

use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Customers\Models\Customer;
use App\Domains\Pricing\Money;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Models\User;

/**
 * How far a cart is from free shipping: the lowest "free over" amount of
 * the active shipping methods (in the cart's currency) that ship to the
 * customer's country, or any country when it is not known yet. Carts with
 * nothing to ship get none.
 */
final class FreeShippingProgress
{
    /**
     * @return array{threshold: Money, remaining: Money}|null null when no method offers free shipping
     */
    public static function for(PricedCart $cart, ?string $country = null): ?array
    {
        if (! $cart->requiresShipping()) {
            return null;
        }

        $thresholds = [];

        $methods = ShippingMethod::query()
            ->where('is_active', true)
            ->where('currency', $cart->currency)
            ->get()
            ->filter(fn (ShippingMethod $method) => $country === null || $method->shipsTo($country));

        foreach ($methods as $method) {
            if ($method->free_over !== null) {
                $thresholds[] = (string) $method->free_over;
            }
        }

        if ($thresholds === []) {
            return null;
        }

        $amounts = array_map(fn (string $amount) => Money::of($amount, $cart->currency), $thresholds);
        $threshold = array_reduce($amounts, fn (?Money $lowest, Money $amount) => $lowest === null || $amount->lessThan($lowest) ? $amount : $lowest);
        // Same basis as the shipping step: merchandise after discounts.
        $remaining = $threshold->minus($cart->subtotal()->minus($cart->discountTotal));

        return [
            'threshold' => $threshold,
            'remaining' => $remaining->isNegative() ? Money::zero($cart->currency) : $remaining,
        ];
    }

    /** The signed-in customer's default shipping country, when there is one. */
    public static function countryFor(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $customer = Customer::query()->where('user_id', $user->getKey())->first();
        $address = $customer?->addresses()->where('is_default_shipping', true)->first();

        return $address?->country_code;
    }
}
