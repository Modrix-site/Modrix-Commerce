<?php

namespace EcoModular\TieredPricing\Models;

use App\Domains\Catalog\Models\Product;
use App\Domains\Pricing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A volume / tiered pricing discount rule.
 */
class TieredPricingRule extends Model
{
    protected $table = 'tiered_pricing_rules';

    protected $guarded = [];

    protected $casts = [
        'target_ids' => 'array',
        'tiers' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Active rules within their scheduled timeframe.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('priority')
            ->orderBy('id');
    }

    /**
     * Check if this rule applies to the given product.
     */
    public function appliesToProduct(Product $product): bool
    {
        if ($this->applies_to === 'all') {
            return true;
        }

        $targets = array_map('intval', (array) ($this->target_ids ?? []));

        if ($this->applies_to === 'product') {
            return in_array((int) $product->id, $targets, true);
        }

        if ($this->applies_to === 'category') {
            $catIds = $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($product->category_id !== null) {
                $catIds[] = (int) $product->category_id;
            }

            return count(array_intersect($targets, $catIds)) > 0;
        }

        return false;
    }

    /**
     * Finds the applicable tier array for a given quantity.
     *
     * @return array{min_qty: int, max_qty: int|null, discount: float}|null
     */
    public function matchingTier(int $quantity): ?array
    {
        $tiers = (array) ($this->tiers ?? []);
        // Sort tiers by min_qty descending to match highest qualifying tier first
        usort($tiers, fn ($a, $b) => ($b['min_qty'] ?? 0) <=> ($a['min_qty'] ?? 0));

        foreach ($tiers as $tier) {
            $min = (int) ($tier['min_qty'] ?? 1);
            $max = isset($tier['max_qty']) && $tier['max_qty'] !== null && $tier['max_qty'] !== ''
                ? (int) $tier['max_qty']
                : null;

            if ($quantity >= $min && ($max === null || $quantity <= $max)) {
                return [
                    'min_qty' => $min,
                    'max_qty' => $max,
                    'discount' => (float) ($tier['discount'] ?? 0),
                ];
            }
        }

        return null;
    }

    /**
     * Calculates unit discount for a matching tier.
     */
    public function calculateUnitDiscount(Money $unitPrice, ?array $tier): Money
    {
        if ($tier === null || ($tier['discount'] ?? 0) <= 0) {
            return Money::zero($unitPrice->currency());
        }

        $discount = (float) $tier['discount'];

        if ($this->discount_type === 'percent') {
            return $unitPrice->percentage((string) $discount)->rounded();
        }

        // Fixed per-unit discount
        return Money::of(
            number_format(min((float) $unitPrice->toDisplayDecimal(), $discount), 2, '.', ''),
            $unitPrice->currency()
        );
    }
}
