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
        $slots->register('product.after_details', self::MODULE, 'ProductReviews', function (array $context) {
            $product = $context['product'] ?? null;

            return $product instanceof Product ? $this->app->make(ReviewSummary::class)->forProduct($product) : null;
        });
    }
}
