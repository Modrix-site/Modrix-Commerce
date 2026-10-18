<?php

namespace EcoModular\FrequentlyBoughtTogether;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\StorefrontCatalog;
use App\Domains\Catalog\VariantAvailability;
use App\Domains\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Works out which products are bought together with a given one.
 *
 * The signal is real order history: for the product being viewed, count how
 * often every other product appears in the same order, keeping only products
 * bought together at least `min_orders` times. The listing is built through
 * StorefrontCatalog so every suggestion is a visible, priced product card that
 * the storefront already knows how to render. When there is too little
 * history and the merchant allows it, related products from the same category
 * fill the gap.
 */
final class BundleRecommender
{
    public function __construct(
        private readonly ModuleSettingsSchema $settings,
        private readonly StorefrontCatalog $catalog,
        private readonly StoreSettings $store,
    ) {}

    /**
     * @return array<string, mixed>|null slot props, or null to show nothing
     */
    public function props(Product $product): ?array
    {
        $max = $this->maxProducts();
        $candidates = $this->coPurchased($product, $max);

        if (count($candidates) < $max && (bool) $this->settings->of(FrequentlyBoughtTogetherServiceProvider::MODULE, 'fallback_related', true)) {
            foreach ($this->related($product, $max) as $related) {
                if (count($candidates) >= $max) {
                    break;
                }
                if (! isset($candidates[$related->id]) && $related->id !== $product->id) {
                    $candidates[$related->id] = $related;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        $items = [];

        foreach ($candidates as $candidate) {
            $card = $this->companion($candidate);

            if ($card !== null) {
                $items[] = $card;
            }
        }

        if ($items === []) {
            return null;
        }

        return [
            'heading' => (string) $this->settings->of(FrequentlyBoughtTogetherServiceProvider::MODULE, 'heading', 'Frequently bought together'),
            'current' => [
                'id' => $product->id,
                'name' => $product->localized('name'),
                'variant_id' => $this->defaultVariantId($product),
                'image' => $this->image($product),
            ],
            'items' => $items,
            'showSavings' => (bool) $this->settings->of(FrequentlyBoughtTogetherServiceProvider::MODULE, 'show_savings', true),
            'addAllUrl' => route('ecomodular.frequently-bought-together.add'),
        ];
    }

    /**
     * Products, ranked by how often they were bought in an order that also
     * contained the given product. Only orders that reached payment count.
     *
     * @return array<int, Product> keyed by product id, in rank order
     */
    private function coPurchased(Product $product, int $limit): array
    {
        $minOrders = max(1, min(20, (int) $this->settings->of(FrequentlyBoughtTogetherServiceProvider::MODULE, 'min_orders', 1)));

        // Orders that include the product being viewed.
        $ordersWith = Order::query()
            ->whereIn('payment_status', ['paid', 'partially_refunded'])
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->select('id');

        // How often every other product appeared in one of those orders.
        $ranked = DB::table('order_items')
            ->select('product_id', DB::raw('COUNT(DISTINCT order_id) as times'))
            ->whereIn('order_id', $ordersWith)
            ->whereNotNull('product_id')
            ->where('product_id', '!=', $product->id)
            ->groupBy('product_id')
            ->having('times', '>=', $minOrders)
            ->orderByDesc('times')
            ->orderBy('product_id')
            ->limit($limit)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ranked === []) {
            return [];
        }

        return $this->loadVisible(array_values($ranked));
    }

    /**
     * Visible products in the same category, newest first, as a fallback.
     *
     * @return array<int, Product>
     */
    private function related(Product $product, int $limit): array
    {
        $category = $product->categories->first();

        if ($category === null) {
            return [];
        }

        $ids = $this->catalog->listing($this->store->currency(), $category, perPage: $limit + 1)
            ->items();

        $products = [];

        foreach ($ids as $card) {
            $id = (int) ($card['id'] ?? 0);
            if ($id !== 0 && $id !== $product->id) {
                $products[$id] = $id;
            }
        }

        return $this->loadVisible(array_values($products));
    }

    /**
     * Loads visible products in the given id order (matching suggestions back
     * to real products, dropping any that are no longer purchasable).
     *
     * @param  list<int>  $ids
     * @return array<int, Product>
     */
    private function loadVisible(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $found = Product::query()
            ->visible()
            ->whereIn('id', $ids)
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'variants.prices',
                'variants.stocks',
                'images' => fn ($q) => $q->limit(1),
                'translations' => fn ($q) => $q->where('locale', app()->getLocale()),
            ])
            ->get()
            ->keyBy('id');

        $ordered = [];

        foreach ($ids as $id) {
            $product = $found->get($id);

            if ($product instanceof Product) {
                $ordered[$id] = $product;
            }
        }

        return $ordered;
    }

    /**
     * Card data for one companion product, including the variant to add.
     *
     * @return array<string, mixed>|null null when it cannot be bought
     */
    private function companion(Product $product): ?array
    {
        $currency = $this->store->currency();
        $variant = $this->purchasableVariant($product, $currency);

        if ($variant === null) {
            return null;
        }

        $card = $this->catalog->card($product, $currency);

        return [
            'id' => $product->id,
            'name' => $product->localized('name'),
            'slug' => $product->slug,
            'url' => route('storefront.product', $product->slug),
            'image' => $this->image($product),
            'variant_id' => $variant->id,
            'price' => $variant->priceIn($currency),
            'compare_at_price' => $variant->compareAtPriceIn($currency),
            'has_variants' => (bool) ($card['has_variants'] ?? false),
        ];
    }

    /**
     * The variant the shopper would add: a single-variant product's only
     * variant, or the default one; only when it is actually purchasable.
     */
    private function purchasableVariant(Product $product, string $currency): ?ProductVariant
    {
        $variants = $product->variants
            ->each(fn (ProductVariant $v) => $v->setRelation('product', $product));

        $default = $variants->firstWhere('is_default', true) ?? $variants->first();

        if (! $default instanceof ProductVariant) {
            return null;
        }

        return VariantAvailability::of($default, $currency)->purchasable ? $default : null;
    }

    private function defaultVariantId(Product $product): ?int
    {
        $variant = $this->purchasableVariant($product, $this->store->currency());

        return $variant?->id;
    }

    /**
     * @return array{url: string, alt: string|null}|null
     */
    private function image(Product $product): ?array
    {
        $image = $product->images->first();

        return $image === null ? null : ['url' => $image->url(), 'alt' => $image->alt];
    }

    private function maxProducts(): int
    {
        return max(1, min(4, (int) $this->settings->of(FrequentlyBoughtTogetherServiceProvider::MODULE, 'max_products', 3)));
    }
}
