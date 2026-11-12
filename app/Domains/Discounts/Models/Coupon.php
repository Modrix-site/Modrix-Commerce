<?php

namespace App\Domains\Discounts\Models;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Pricing\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string|null $description
 * @property string $type
 * @property string $value
 * @property string|null $currency
 * @property string|null $min_subtotal
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_customer
 * @property int $times_used
 * @property string $applies_to
 * @property bool $is_active
 */
#[Fillable(['code', 'description', 'type', 'value', 'currency', 'min_subtotal', 'starts_at', 'ends_at', 'usage_limit', 'usage_limit_per_customer', 'applies_to', 'is_active'])]
class Coupon extends Model
{
    use SoftDeletes;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const APPLIES_ALL = 'all';

    public const APPLIES_PRODUCTS = 'products';

    public const APPLIES_CATEGORIES = 'categories';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'min_subtotal' => 'decimal:4',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'times_used' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_categories');
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function minSubtotal(string $currency): ?Money
    {
        return $this->min_subtotal === null ? null : Money::of($this->min_subtotal, $currency);
    }
}
