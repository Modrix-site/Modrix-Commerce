<?php

namespace EcoModular\CrossSellUpsell\Models;

use App\Domains\Catalog\Models\Product;
use App\Domains\Pricing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A merchant rule: when a trigger product is viewed or in the cart, suggest
 * these products (picked, or found automatically), optionally at a discount.
 *
 * @property int $id
 * @property string $name
 * @property string|null $heading
 * @property string $type
 * @property string $trigger_type
 * @property list<int> $trigger_ids
 * @property string $suggestion_mode
 * @property list<int> $product_ids
 * @property bool $show_on_product
 * @property bool $show_in_popup
 * @property bool $show_in_cart
 * @property bool $show_at_checkout
 * @property string|null $discount_type
 * @property string|null $discount_value
 * @property int $priority
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property int $views
 * @property int $adds
 */
class Offer extends Model
{
    public const UPSELL = 'upsell';

    public const CROSS_SELL = 'cross_sell';

    public const TRIGGER_ANY = 'any';

    public const TRIGGER_PRODUCTS = 'products';

    public const TRIGGER_CATEGORIES = 'categories';

    public const MANUAL = 'manual';

    public const AUTOMATIC = 'automatic';

    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    /** Most products picked by hand. */
    public const MAX_PRODUCTS = 12;

    /** Where an offer can show => its switch. */
    public const PLACES = [
        'product' => 'show_on_product',
        'popup' => 'show_in_popup',
        'cart' => 'show_in_cart',
        'checkout' => 'show_at_checkout',
    ];

    protected $table = 'cross_sell_offers';

    protected $fillable = [
        'name', 'heading', 'type', 'trigger_type', 'trigger_ids', 'suggestion_mode', 'product_ids',
        'show_on_product', 'show_in_popup', 'show_in_cart', 'show_at_checkout',
        'discount_type', 'discount_value', 'priority', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $attributes = ['trigger_ids' => '[]', 'product_ids' => '[]'];

    /** @var array<int, list<int>> product id => category ids, per instance */
    private array $categoryCache = [];

    protected function casts(): array
    {
        return [
            'trigger_ids' => 'array',
            'product_ids' => 'array',
            'show_on_product' => 'boolean',
            'show_in_popup' => 'boolean',
            'show_in_cart' => 'boolean',
            'show_at_checkout' => 'boolean',
            'discount_value' => 'decimal:4',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'views' => 'integer',
            'adds' => 'integer',
        ];
    }

    /**
     * Active and within its dates.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'off',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at !== null && $this->ends_at->isPast() => 'ended',
            default => 'live',
        };
    }

    public function showsIn(string $place): bool
    {
        $field = self::PLACES[$place] ?? null;

        return $field !== null && (bool) $this->{$field};
    }

    /** The heading shoppers see. */
    public function label(): string
    {
        $heading = trim((string) $this->heading);

        if ($heading !== '') {
            return $heading;
        }

        return $this->type === self::UPSELL ? st('Upgrade your choice') : st('Goes well with');
    }

    public function hasDiscount(): bool
    {
        return $this->discount_type !== null && $this->discount_value !== null && (float) $this->discount_value > 0;
    }

    /**
     * The discount on one unit at this price (never more than the price).
     */
    public function discountFor(Money $unitPrice): Money
    {
        if (! $this->hasDiscount()) {
            return Money::zero($unitPrice->currency());
        }

        $discount = $this->discount_type === self::PERCENT
            ? $unitPrice->percentage((string) $this->discount_value)->rounded()
            : Money::of((string) $this->discount_value, $unitPrice->currency())->rounded();

        return $discount->min($unitPrice)->nonNegative();
    }

    /** Whether viewing or buying this product brings up the offer. */
    public function isTriggeredBy(Product $product): bool
    {
        return match ($this->trigger_type) {
            self::TRIGGER_ANY => true,
            self::TRIGGER_PRODUCTS => in_array($product->id, $this->ids('trigger_ids'), true),
            self::TRIGGER_CATEGORIES => array_intersect($this->categoriesOf($product), $this->ids('trigger_ids')) !== [],
            default => false,
        };
    }

    /**
     * Whether the offer may suggest this product (any trigger). Picked lists
     * are exact; automatic offers suggest products from the trigger's categories.
     */
    public function maySuggest(Product $product): bool
    {
        if ($this->suggestion_mode === self::MANUAL) {
            return in_array($product->id, $this->ids('product_ids'), true);
        }

        return match ($this->trigger_type) {
            self::TRIGGER_ANY => true,
            self::TRIGGER_CATEGORIES => array_intersect($this->categoriesOf($product), $this->ids('trigger_ids')) !== [],
            default => array_intersect($this->categoriesOf($product), $this->triggerProductCategories()) !== [],
        };
    }

    /** @return list<int> */
    public function ids(string $field): array
    {
        return array_values(array_map('intval', (array) $this->{$field}));
    }

    /** @return list<int> */
    public function categoriesOf(Product $product): array
    {
        if ($product->relationLoaded('categories')) {
            return array_values(array_map('intval', $product->categories->pluck('id')->all()));
        }

        return $this->categoryCache[$product->id] ??= array_values(array_map('intval', DB::table('product_categories')->where('product_id', $product->id)->pluck('category_id')->all()));
    }

    /** @return list<int> */
    private function triggerProductCategories(): array
    {
        return array_values(array_map('intval', DB::table('product_categories')->whereIn('product_id', $this->ids('trigger_ids'))->distinct()->pluck('category_id')->all()));
    }
}
