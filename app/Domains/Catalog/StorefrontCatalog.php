<?php

namespace App\Domains\Catalog;

use App\Domains\Catalog\Contracts\SearchProvider;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Pricing\SalePrices;
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
    public function listing(string $currency, ?Category $category = null, ?string $search = null, string $sort = 'newest', int $perPage = 24, ?Brand $brand = null): LengthAwarePaginator
    {
        $query = $this->visibleWithPrice($currency);

        if ($brand !== null) {
            $query->where('products.brand_id', $brand->id);
        }

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

        $page = $query->paginate($perPage)->withQueryString();
        // One sale-price lookup for the whole page.
        app(SalePrices::class)->preload($page->getCollection()->pluck('id'));

        return $page->through(fn (Product $product) => $this->card($product, $currency));
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
        app(SalePrices::class)->preload($products->pluck('id'));

        return array_values(array_map(fn (Product $p) => $this->card($p, $currency), $products->all()));
    }

    /**
     * Products the merchant marked as featured, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function featured(string $currency, int $limit = 8): array
    {
        $products = $this->visibleWithPrice($currency)
            ->where('products.is_featured', true)
            ->orderByDesc('products.published_at')
            ->orderByDesc('products.id')
            ->limit($limit)
            ->get();
        app(SalePrices::class)->preload($products->pluck('id'));

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
                'brand:id,name,slug',
                'categories' => fn ($q) => $q->where('is_active', true),
                'categories.translations' => fn ($q) => $q->where('locale', app()->getLocale()),
                'translations' => fn ($q) => $q->where('locale', app()->getLocale()),
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
        $sales = app(SalePrices::class);
        // Sale prices (from modules such as flash sales) replace the regular price.
        $prices = $variants->map(function (ProductVariant $v) use ($currency, $sales, $product) {
            $price = $v->priceIn($currency);

            return $price === null ? null : ($sales->apply($product->id, $price)['price'] ?? $price);
        })->filter();
        $from = $prices->reduce(fn (?Money $carry, Money $m) => $carry === null || $m->lessThan($carry) ? $m : $carry);
        $default = $variants->first();
        $compareAt = $default?->compareAtPriceIn($currency);
        $sale = $default === null ? null : $sales->forVariant($default, $currency);
        if ($sale !== null) {
            // The price before the sale is shown crossed out (or the compare-at price, if higher).
            $compareAt = $compareAt !== null && $compareAt->greaterThan($sale['original']) ? $compareAt : $sale['original'];
        }
        $image = $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->localized('name'),
            'slug' => $product->slug,
            'short_description' => $product->localized('short_description'),
            'image' => $image instanceof ProductImage ? ['url' => $image->url(), 'alt' => $image->alt] : null,
            'price' => $from,
            'compare_at_price' => $variants->count() === 1 ? $compareAt : null,
            'sale' => $this->saleBadge($product, $variants, $currency),
            'has_variants' => $variants->count() > 1,
            'in_stock' => $variants->contains(fn (ProductVariant $v) => VariantAvailability::of($v, $currency)->purchasable),
        ];
    }

    /**
     * Cards for these products (visible ones only, in the given order), e.g. a
     * module's own product list.
     *
     * @param  list<int>  $productIds
     * @return list<array<string, mixed>>
     */
    public function cardsFor(array $productIds, string $currency, int $limit = 12): array
    {
        if ($productIds === []) {
            return [];
        }

        // Only the first $limit visible products (in the given order) are loaded.
        $visible = array_map('intval', $this->visibleWithPrice($currency)->whereIn('products.id', $productIds)->pluck('products.id')->all());
        $ids = array_slice(array_values(array_intersect($productIds, $visible)), 0, $limit);

        if ($ids === []) {
            return [];
        }

        app(SalePrices::class)->preload($ids);
        $products = $this->visibleWithPrice($currency)->whereIn('products.id', $ids)->get()->keyBy('id');
        $cards = [];

        foreach ($ids as $id) {
            if ($products->has($id)) {
                $cards[] = $this->card($products->get($id), $currency);
            }
        }

        return $cards;
    }

    /**
     * The sale shown on a card ("−20%" badge and end time), if any variant is on sale.
     *
     * @param  iterable<ProductVariant>  $variants
     * @return array{badge: string, name: string, ends_at: string|null}|null
     */
    private function saleBadge(Product $product, iterable $variants, string $currency): ?array
    {
        $sales = app(SalePrices::class);

        foreach ($variants as $variant) {
            $sale = $sales->forVariant($variant, $currency);

            if ($sale !== null) {
                return [
                    'badge' => $sale['offer']->badge(),
                    'name' => $sale['offer']->name,
                    'ends_at' => $sale['offer']->endsAt?->toIso8601String(),
                ];
            }
        }

        return null;
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
                'translations' => fn ($q) => $q->where('locale', app()->getLocale()),
            ]);
    }
}
