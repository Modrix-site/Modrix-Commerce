<?php

namespace App\Domains\Checkout;

use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Customers\Models\Customer;
use App\Domains\Pricing\Money;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Domains\Shipping\ShippingRegions;
use App\Models\User;

/**
 * How far a cart is from free shipping: the lowest "free over" amount of
 * the active shipping methods (in the cart's currency) that ship to the
 * customer's country, or any country when it is not known yet. A method's
 * regional rule replaces its amount exactly as in TableShippingRateProvider.
 * Carts with nothing to ship get none.
 */
final class FreeShippingProgress
{
    /**
     * @return array{threshold: Money, remaining: Money}|null null when no method offers free shipping
     */
    public static function for(PricedCart $cart, ?string $country = null, ?string $region = null): ?array
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
            $regional = $country === null ? null : self::regionalRule($method, $country, $region);

            // Regions-only methods are offered only where a rule matches.
            if ($method->regions_only && $regional === null) {
                continue;
            }

            $threshold = $regional['free_over'] ?? $method->free_over;

            if ($threshold !== null) {
                $thresholds[] = (string) $threshold;
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

    /**
     * The rule the rate provider would use for this destination.
     *
     * @return array{country_code: string, region: string, rate: string, free_over: string|null}|null
     */
    private static function regionalRule(ShippingMethod $method, string $country, ?string $region): ?array
    {
        foreach ($method->regional_rates ?? [] as $rule) {
            if ($rule['country_code'] === strtoupper($country)
                && ShippingRegions::normalize($rule['region'], $country) === ShippingRegions::normalize($region, $country)) {
                return $rule;
            }
        }

        return null;
    }

    /** The signed-in customer's default shipping country, when there is one. */
    public static function countryFor(?User $user): ?string
    {
        return self::destinationFor($user)['country'];
    }

    /**
     * The signed-in customer's default shipping country and region.
     *
     * @return array{country: string|null, region: string|null}
     */
    public static function destinationFor(?User $user): array
    {
        $address = $user === null ? null : Customer::query()->where('user_id', $user->getKey())->first()
            ?->addresses()->where('is_default_shipping', true)->first();

        return ['country' => $address?->country_code, 'region' => $address?->region];
    }
}
