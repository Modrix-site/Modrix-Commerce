<?php

namespace EcoModular\TieredPricing;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Money;
use EcoModular\TieredPricing\Models\TieredPricingRule;

/**
 * Resolves active tiered pricing rules for a product and formats widget props.
 */
class TieredPricingResolver
{
    public function __construct(
        private readonly TieredPricingSettings $settings,
        private readonly StoreSettings $store,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolveForProduct(Product $product): ?array
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        $rules = TieredPricingRule::query()->live()->get();
        /** @var TieredPricingRule|null $rule */
        $rule = $rules->first(fn (TieredPricingRule $r) => $r->appliesToProduct($product));

        if ($rule === null || empty($rule->tiers)) {
            return null;
        }

        $currency = $this->store->currency();
        $variant = $this->defaultVariant($product);
        if ($variant === null) {
            return null;
        }

        $basePrice = $variant->priceIn($currency) ?? Money::zero($currency);
        if (! $basePrice->isPositive()) {
            return null;
        }

        // Sort tiers by min_qty ascending for display
        $tiers = (array) $rule->tiers;
        usort($tiers, fn ($a, $b) => ($a['min_qty'] ?? 0) <=> ($b['min_qty'] ?? 0));

        $formattedTiers = [];
        foreach ($tiers as $tier) {
            $min = (int) ($tier['min_qty'] ?? 1);
            $max = isset($tier['max_qty']) && $tier['max_qty'] !== null && $tier['max_qty'] !== ''
                ? (int) $tier['max_qty']
                : null;
            $discount = (float) ($tier['discount'] ?? 0);

            if ($discount <= 0) {
                continue;
            }

            $unitDiscount = $rule->calculateUnitDiscount($basePrice, $tier);
            $tierUnitPrice = $basePrice->minus($unitDiscount)->nonNegative();

            $rangeLabel = $max !== null
                ? ($min === $max ? "Qty {$min}" : "Buy {$min} - {$max}")
                : "Buy {$min}+";

            $badgeLabel = $rule->discount_type === 'percent'
                ? 'Save '.rtrim(rtrim(number_format($discount, 2), '0'), '.').'%'
                : 'Save '.$unitDiscount->format().'/ea';

            $formattedTiers[] = [
                'min_qty' => $min,
                'max_qty' => $max,
                'range_label' => $rangeLabel,
                'badge_label' => $badgeLabel,
                'discount' => $discount,
                'discount_type' => $rule->discount_type,
                'unit_price' => (float) $tierUnitPrice->toDisplayDecimal(),
                'unit_price_formatted' => $tierUnitPrice->format(),
                'savings_per_unit' => (float) $unitDiscount->toDisplayDecimal(),
                'savings_per_unit_formatted' => $unitDiscount->format(),
            ];
        }

        if (empty($formattedTiers)) {
            return null;
        }

        return [
            'rule_id' => $rule->id,
            'rule_title' => $rule->title,
            'heading' => $this->settings->tableHeading(),
            'subheading' => $this->settings->tableSubheading(),
            'show_savings_badge' => $this->settings->showSavingsBadge(),
            'highlight_active_tier' => $this->highlightActiveTier(),
            'currency' => $currency,
            'base_unit_price' => (float) $basePrice->toDisplayDecimal(),
            'base_unit_price_formatted' => $basePrice->format(),
            'tiers' => $formattedTiers,
        ];
    }

    private function highlightActiveTier(): bool
    {
        return $this->settings->highlightActiveTier();
    }

    private function defaultVariant(Product $product): ?ProductVariant
    {
        return $product->variants
            ->where('is_active', true)
            ->first();
    }
}
