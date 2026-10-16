<?php

namespace App\Http\Controllers\Storefront;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\ProductAttributeValue;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Pricing\SalePrices;
use App\Domains\Pricing\Money;
use App\Domains\Catalog\StorefrontCatalog;
use App\Domains\Catalog\VariantAvailability;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * A variant's price, with any sale price (e.g. a flash sale) applied and
     * the regular price shown crossed out.
     *
     * @return array{price: Money|null, compare_at_price: Money|null}
     */
    private static function variantPrices(ProductVariant $variant, string $currency): array
    {
        $compareAt = $variant->compareAtPriceIn($currency);
        $sale = app(SalePrices::class)->forVariant($variant, $currency);

        if ($sale === null) {
            return ['price' => $variant->priceIn($currency), 'compare_at_price' => $compareAt];
        }

        return [
            'price' => $sale['price'],
            'compare_at_price' => $compareAt !== null && $compareAt->greaterThan($sale['original']) ? $compareAt : $sale['original'],
        ];
    }

    /**
     * @return array{badge: string, name: string, label: string, ends_at: string|null, countdown: bool}|null
     */
    private static function sale(Product $product, string $currency): ?array
    {
        foreach ($product->variants as $variant) {
            $sale = app(SalePrices::class)->forVariant($variant, $currency);

            if ($sale !== null) {
                return $sale['offer']->toProps();
            }
        }

        return null;
    }

    public function show(string $slug, StorefrontCatalog $catalog, StoreSettings $store, StorefrontSlots $slots): Response
    {
        $product = $catalog->findVisible($slug);
        abort_if($product === null, 404);

        $currency = $store->currency();
        $category = $product->categories->first();

        // Option groups in a stable order, built from the values the active variants use.
        $options = $product->variants
            ->flatMap(fn (ProductVariant $v) => $v->options)
            ->unique('id')
            ->groupBy('attribute_id')
            ->map(fn ($values) => [
                'id' => $values->first()->attribute->id,
                'name' => $values->first()->attribute->name,
                'position' => $values->first()->attribute->position,
                'values' => $values->sortBy('position')
                    ->map(fn (ProductAttributeValue $v) => ['id' => $v->id, 'value' => $v->value])
                    ->values(),
            ])
            ->sortBy('position')
            ->values();

        return Inertia::render('storefront/product', [
            'product' => [
                'id' => $product->id,
                'name' => $product->localized('name'),
                'slug' => $product->slug,
                'short_description' => $product->localized('short_description'),
                'description' => $product->localized('description'),
                'meta_title' => $product->localized('meta_title') ?: $product->localized('name'),
                'meta_description' => $product->localized('meta_description') ?: $product->localized('short_description'),
                'images' => $product->images->map(fn (ProductImage $i) => ['id' => $i->id, 'url' => $i->url(), 'alt' => $i->alt])->values(),
                'sale' => self::sale($product, $currency),
                'brand' => $product->brand ? ['name' => $product->brand->name, 'slug' => $product->brand->slug] : null,
                'options' => $options,
                'variants' => $product->variants->map(function (ProductVariant $v) use ($currency) {
                    $availability = VariantAvailability::of($v, $currency);

                    return [
                        'id' => $v->id,
                        'name' => $v->name,
                        'sku' => $v->sku,
                        ...self::variantPrices($v, $currency),
                        'option_value_ids' => $v->options->pluck('id')->values(),
                        'purchasable' => $availability->purchasable,
                        'available' => $availability->available,
                        'message' => $availability->message(),
                    ];
                })->values(),
            ],
            'breadcrumbs' => $category instanceof Category
                ? collect($category->ancestors())->push($category)
                    ->map(fn (Category $c) => ['name' => $c->localized('name'), 'slug' => $c->slug])->values()
                : [],
            'slots' => $slots->render('product.after_details', ['product' => $product]),
        ]);
    }
}
