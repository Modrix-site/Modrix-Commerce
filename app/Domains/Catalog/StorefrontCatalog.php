<?php

namespace App\Domains\Catalog;

use App\Domains\Catalog\Contracts\SearchProvider;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Money;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the storefront: visible products, search, sorting and
 * product-card data. Matching is delegated to the bound SearchProvider
 * (LIKE search by default; modules can replace it).
 */
final class StorefrontCatalog
{
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'name'];

    public function __construct(private readonly SearchProvider $search) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function listing(string $currency, ?Category $category = null, ?string $search = null, string $sort = 'newest', int $perPage = 24): LengthAwarePaginator
    {
        $query = $this->visibleWithPrice($currency);

        if ($category !== null) {
            $ids = $category->descendantIdsAndSelf();
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids));
        }

        if ($search !== null && trim($search) !== '') {
            // Wrapped so a provider's OR conditions cannot escape the visibility constraints.
            $query->where(fn (Builder $q) => $this->search->apply($q, trim($search)));
        }

        match ($sort) {
            'price_asc' => $query->orderBy('min_price')->orderBy('products.id'),
            'price_desc' => $query->orderByDesc('min_price')->orderBy('products.id'),
            'name' => $query->orderBy('products.name')->orderBy('products.id'),
            default => $query->orderByDesc('products.published_at')->orderByDesc('products.id'),
        };

        return $query->paginate($perPage)->withQueryString()
            ->through(fn (Product $product) => $this->card($product, $currency));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(string $currency, int $limit = 8): array
    {
        $products = $this->visibleWithPrice($currency)
            ->orderByDesc('products.published_at')
            ->orderByDesc('products.id')
            ->limit($limit)
            ->get();

        return array_values(array_map(fn (Product $p) => $this->card($p, $currency), $products->all()));
    }

    public function findVisible(string $slug): ?Product
    {
        $product = Product::query()
            ->visible()
            ->where('slug', $slug)
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'variants.prices',
                'variants.stocks',
                'variants.options.attribute',
                'images',
                'categories' => fn ($q) => $q->where('is_active', true),
            ])
            ->first();

        $product?->variants->each(fn (ProductVariant $v) => $v->setRelation('product', $product));

        return $product;
    }

    /**
     * Card data for listings (never includes cost or internal fields).
     *
     * @return array<string, mixed>
     */
    public function card(Product $product, string $currency): array
    {
        // Reuse the loaded parent instead of querying it again per variant.
        $variants = $product->variants->each(fn (ProductVariant $v) => $v->setRelation('product', $product));
        $prices = $variants->map(fn (ProductVariant $v) => $v->priceIn($currency))->filter();
        $from = $prices->reduce(fn (?Money $carry, Money $m) => $carry === null || $m->lessThan($carry) ? $m : $carry);
        $default = $variants->first();
        $compareAt = $default?->compareAtPriceIn($currency);
        $image = $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'image' => $image instanceof ProductImage ? ['url' => $image->url(), 'alt' => $image->alt] : null,
            'price' => $from,
            'compare_at_price' => $variants->count() === 1 ? $compareAt : null,
            'has_variants' => $variants->count() > 1,
            'in_stock' => $variants->contains(fn (ProductVariant $v) => VariantAvailability::of($v, $currency)->purchasable),
        ];
    }

    /**
     * Visible products that have at least one active variant priced in the currency,
     * with a `min_price` column for sorting.
     *
     * @return Builder<Product>
     */
    private function visibleWithPrice(string $currency): Builder
    {
        $minPrice = DB::table('product_prices')
            ->join('product_variants', 'product_variants.id', '=', 'product_prices.variant_id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('product_prices.currency', $currency)
            ->where('product_variants.is_active', true)
            ->whereNull('product_variants.deleted_at')
            ->selectRaw('MIN(product_prices.amount)');

        return Product::query()
            ->visible()
            ->select('products.*')
            ->selectSub($minPrice, 'min_price')
            ->whereHas('variants', fn ($q) => $q->where('is_active', true)
                ->whereHas('prices', fn ($p) => $p->where('currency', $currency)))
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'variants.prices',
                'variants.stocks',
                'images' => fn ($q) => $q->limit(1),
            ]);
    }
}
