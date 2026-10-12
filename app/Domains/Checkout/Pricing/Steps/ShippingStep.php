<?php

namespace App\Domains\Checkout\Pricing\Steps;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\PricingStep;
use App\Domains\Shipping\ShippingRate;
use App\Domains\Shipping\ShippingRates;
use App\Domains\Shipping\ShippingRequest;
use Brick\Math\BigDecimal;

/**
 * Quotes shipping for the destination and applies the chosen method.
 * Without a destination no options are offered yet (shown as "at checkout").
 */
final class ShippingStep implements PricingStep
{
    public function __construct(private readonly ShippingRates $rates) {}

    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void
    {
        if ($context->countryCode === null || $priced->purchasableLines() === []) {
            return;
        }

        $weight = BigDecimal::zero();
        $items = 0;

        foreach ($priced->purchasableLines() as $line) {
            $weight = $weight->plus(BigDecimal::of($line->variant->weight ?? '0')->multipliedBy($line->quantity));
            $items += $line->quantity;
        }

        $options = $this->rates->quote(new ShippingRequest(
            currency: $priced->currency,
            countryCode: $context->countryCode,
            region: $context->region,
            postalCode: $context->postalCode,
            merchandiseTotal: $priced->subtotal()->minus($priced->discountTotal),
            totalWeight: (string) $weight,
            itemCount: $items,
        ));

        $priced->shippingOptions = array_map(fn (ShippingRate $r) => $r->toArray(), $options);

        if ($options === []) {
            $priced->issues[] = 'We do not ship to the selected country.';

            return;
        }

        $selected = null;

        foreach ($options as $option) {
            if ($option->code === $context->shippingMethod) {
                $selected = $option;
            }
        }

        if ($selected !== null) {
            $priced->shipping = $selected->toArray();
            $priced->shippingTotal = $selected->amount;
        }
    }
}
