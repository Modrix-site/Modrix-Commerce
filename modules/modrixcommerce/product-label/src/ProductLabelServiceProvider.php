<?php

namespace EcoModular\ProductLabel;

use App\Core\Catalog\ProductLabels;
use App\Domains\Catalog\Models\Product;
use Illuminate\Support\ServiceProvider;

/**
 * Contributes product badges to storefront cards through the core
 * ProductLabels registry. Which labels appear and how they look is decided by
 * LabelResolver from the merchant's settings; the themes only render them.
 */
class ProductLabelServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/product-label';

    public function register(): void
    {
        // Resolved for every product card on a page: build it once per request.
        $this->app->scoped(LabelResolver::class);
    }

    public function boot(ProductLabels $labels): void
    {
        $labels->register(self::MODULE, function (mixed $product): array {
            return $product instanceof Product
                ? $this->app->make(LabelResolver::class)->for($product)
                : [];
        });
    }
}
