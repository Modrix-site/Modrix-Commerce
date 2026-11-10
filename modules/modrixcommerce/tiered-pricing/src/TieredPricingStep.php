<?php

namespace EcoModular\TieredPricing;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricedLine;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\PricingStep;
use EcoModular\TieredPricing\Models\TieredPricingRule;

/**
 * Pricing step that applies volume discounts to cart lines.
 */
class TieredPricingStep implements PricingStep
{
    public function __construct(
        private readonly TieredPricingSettings $settings,
    ) {}

    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void
    {
        if (! $cart->exists || empty($priced->lines)) {
            return;
        }

        if (! $this->settings->enabled()) {
            return;
        }

        $rules = TieredPricingRule::query()->live()->get();
        if ($rules->isEmpty()) {
            return;
        }

        foreach ($priced->lines as $line) {
            if (! $line instanceof PricedLine || ! $line->isPurchasable() || $line->unitPrice === null) {
                continue;
            }

            $product = $line->variant->product;
            if ($product === null) {
                continue;
            }

            /** @var TieredPricingRule|null $matchingRule */
            $matchingRule = $rules->first(fn (TieredPricingRule $rule) => $rule->appliesToProduct($product));
            if ($matchingRule === null) {
                continue;
            }

            $tier = $matchingRule->matchingTier($line->quantity);
            if ($tier === null) {
                continue;
            }

            $basePrice = $line->baseUnitPrice ?? $line->unitPrice;
            $unitDiscount = $matchingRule->calculateUnitDiscount($basePrice, $tier);

            if (! $unitDiscount->isPositive()) {
                continue;
            }

            $lineTotalDiscount = $unitDiscount->multipliedBy($line->quantity)->rounded();
            if ($lineTotalDiscount->isPositive()) {
                $label = 'Volume Discount (' . $tier['min_qty'] . '+)';
                $line->applyOfferDiscount($priced, $lineTotalDiscount, $label);
            }
        }
    }
}
