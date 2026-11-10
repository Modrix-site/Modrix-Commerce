<?php

namespace EcoModular\BuyTogetherSave\Models;

use App\Domains\Catalog\Models\Product;
use App\Domains\Pricing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A curated or promotional "Buy Together and Save" bundle offer.
 */
class BuyTogetherDeal extends Model
{
    protected $table = 'buy_together_deals';

    protected $guarded = [];

    protected $casts = [
        'trigger_ids' => 'array',
        'bundle_product_ids' => 'array',
        'discount_value' => 'decimal:4',
        'min_items_required' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'views_count' => 'integer',
        'conversions_count' => 'integer',
    ];

    /**
     * Deals currently active and within schedule.
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
     * Determines whether this deal targets the specified product.
     */
    public function appliesToProduct(Product $product): bool
    {
        if ($this->trigger_type === 'all') {
            return true;
        }

        $triggerIds = array_map('intval', $this->trigger_ids ?? []);

        if ($this->trigger_type === 'product') {
            return in_array((int) $product->id, $triggerIds, true);
        }

        if ($this->trigger_type === 'category') {
            $categoryIds = $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($product->category_id !== null) {
                $categoryIds[] = (int) $product->category_id;
            }

            return array_intersect($triggerIds, $categoryIds) !== [];
        }

        return false;
    }

    /**
     * Calculates the savings amount for a given combined subtotal.
     */
    public function calculateSavings(Money $subtotal): Money
    {
        if ($this->discount_type === 'percent') {
            $percentage = max(0, min(100, (float) $this->discount_value));
            $ratio = $percentage / 100.0;

            return $subtotal->multipliedBy($ratio)->rounded();
        }

        // Fixed amount discount
        $fixed = max(0.0, (float) $this->discount_value);
        $discountMoney = Money::of(number_format($fixed, 2, '.', ''), $subtotal->currency());

        return $discountMoney->min($subtotal);
    }

    /**
     * Returns human-readable label for the deal's savings.
     */
    public function badgeLabel(): string
    {
        if (! empty($this->badge_text)) {
            return $this->badge_text;
        }

        if ($this->discount_type === 'percent') {
            $pct = (int) round((float) $this->discount_value);
            return "Save {$pct}%";
        }

        return 'Bundle Savings';
    }
}
