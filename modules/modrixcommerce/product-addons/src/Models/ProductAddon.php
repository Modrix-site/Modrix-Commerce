<?php

namespace EcoModular\ProductAddons\Models;

use App\Domains\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ProductAddon extends Model
{
    public const TYPE_WARRANTY = 'warranty';

    public const TYPE_FEATURE = 'feature';

    public const TYPE_SERVICE = 'service';

    public const TARGET_ALL = 'all';

    public const TARGET_PRODUCTS = 'products';

    public const TARGET_CATEGORIES = 'categories';

    protected $table = 'product_addons';

    protected $fillable = [
        'name',
        'type',
        'badge',
        'description',
        'price',
        'target_type',
        'target_ids',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'target_ids' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Return all active addons that apply to the given product, ordered by priority DESC then id ASC.
     *
     * @param  list<int>|null  $categoryIds
     * @return Collection<int, self>
     */
    public static function forProduct(Product $product, ?array $categoryIds = null): Collection
    {
        $categoryIds ??= $product->relationLoaded('categories')
            ? $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $product->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();

        return self::query()
            ->active()
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (self $addon) => $addon->matchesProduct((int) $product->id, $categoryIds))
            ->values();
    }

    /**
     * @param  list<int>  $categoryIds
     */
    public function matchesProduct(int $productId, array $categoryIds = []): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $targets = array_map('intval', $this->target_ids ?? []);

        return match ($this->target_type) {
            self::TARGET_ALL => true,
            self::TARGET_PRODUCTS => in_array($productId, $targets, true),
            self::TARGET_CATEGORIES => count(array_intersect($categoryIds, $targets)) > 0,
            default => false,
        };
    }
}
