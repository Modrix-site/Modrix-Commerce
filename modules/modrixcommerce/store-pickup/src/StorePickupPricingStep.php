<?php

namespace EcoModular\StorePickup;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\PricingStep;
use App\Domains\Pricing\Money;

/**
 * Waives cash on delivery courier fee when in-store or branch pickup is selected.
 */
final class StorePickupPricingStep implements PricingStep
{
    public function __construct(
        private readonly StorePickupSettings $settings,
    ) {}

    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void
    {
        if (! $this->settings->enabled()) {
            return;
        }

        $method = $context->shippingMethod ?? $priced->shipping['code'] ?? null;

        $isPickup = $method === StorePickupShippingRateProvider::METHOD_CODE
            || (is_string($method) && str_starts_with($method, 'pickup_'));

        if (! $isPickup) {
            return;
        }

        $behavior = $this->settings->codBehavior();
        if ($behavior === StorePickupSettings::COD_KEEP) {
            return;
        }

        // Waive courier collection fee for in-store pickup
        if ($context->paymentMethod === 'cash_on_delivery' || $priced->paymentMethod === 'cash_on_delivery') {
            $priced->paymentFeeTotal = Money::zero($priced->currency);
        }
    }
}
