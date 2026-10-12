<?php

namespace App\Http\Controllers\Storefront;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\ProductAttributeValue;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\StorefrontCatalog;
use App\Domains\Catalog\VariantAvailability;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(string $slug, StorefrontCatalog $catalog, StoreSettings $store): Response
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
                'name' => $product->name,
                'slug' => $product->slug,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'meta_title' => $product->meta_title ?: $product->name,
                'meta_description' => $product->meta_description ?: $product->short_description,
                'images' => $product->images->map(fn (ProductImage $i) => ['id' => $i->id, 'url' => $i->url(), 'alt' => $i->alt])->values(),
                'options' => $options,
                'variants' => $product->variants->map(function (ProductVariant $v) use ($currency) {
                    $availability = VariantAvailability::of($v, $currency);

                    return [
                        'id' => $v->id,
                        'name' => $v->name,
                        'sku' => $v->sku,
                        'price' => $v->priceIn($currency),
                        'compare_at_price' => $v->compareAtPriceIn($currency),
                        'option_value_ids' => $v->options->pluck('id')->values(),
                        'purchasable' => $availability->purchasable,
                        'available' => $availability->available,
                        'message' => $availability->message(),
                    ];
                })->values(),
            ],
            'breadcrumbs' => $category instanceof Category
                ? collect($category->ancestors())->push($category)
                    ->map(fn (Category $c) => ['name' => $c->name, 'slug' => $c->slug])->values()
                : [],
        ]);
    }
}
