<?php

namespace EcoModular\ProductReviews;

use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use Illuminate\Support\ServiceProvider;

/**
 * Routes, permissions, navigation and settings come from module.json; the
 * provider only wires the storefront slot.
 */
class ProductReviewsServiceProvider extends ServiceProvider
{
    public const MODULE = 'ecomodular/product-reviews';

    public function boot(StorefrontSlots $slots): void
    {
        $slots->block(self::MODULE, 'reviews', 'Product reviews', 'ProductReviews', ['product.after_details', 'product.after_description'], 'product.after_details', function (array $context) {
            $product = $context['product'] ?? null;

            return $product instanceof Product ? $this->app->make(ReviewSummary::class)->forProduct($product) : null;
        });
    }
}
