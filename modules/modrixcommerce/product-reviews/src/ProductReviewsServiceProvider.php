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
    public const MODULE = 'modrixcommerce/product-reviews';

    public function boot(StorefrontSlots $slots): void
    {
        $slots->block(self::MODULE, 'reviews', 'Product reviews', 'ProductReviews', ['product.after_details', 'product.after_description'], 'product.after_details', function (array $context) {
            $product = $context['product'] ?? null;

            return $product instanceof Product ? $this->app->make(ReviewSummary::class)->forProduct($product) : null;
        });

        // Stars and the review count near the title; the merchant can switch them off or move them.
        $slots->block(self::MODULE, 'rating', 'Rating stars', 'RatingStars', ['product.after_title', 'product.after_price'], 'product.after_title', function (array $context) {
            $product = $context['product'] ?? null;

            return $product instanceof Product ? $this->app->make(ReviewSummary::class)->rating($product) : null;
        });
    }
}
