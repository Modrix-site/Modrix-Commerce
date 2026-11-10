<?php

namespace EcoModular\TieredPricing;

use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use App\Domains\Checkout\Pricing\CartPricer;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Tiered Pricing & Volume Discounts.
 */
class TieredPricingServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/tiered-pricing';

    public function register(): void
    {
        $this->app->singleton(TieredPricingSettings::class);
        $this->app->singleton(TieredPricingResolver::class);
        $this->app->singleton(TieredPricingStep::class);
    }

    public function boot(StorefrontSlots $slots, CartPricer $pricer): void
    {
        // 1. Register customizable storefront block for product pages
        $slots->block(
            self::MODULE,
            'tiered_pricing_table',
            'Tiered Pricing Table',
            'TieredPricingTable',
            [
                'product.after_price',
                'product.after_add_to_cart',
                'product.after_details',
                'product.after_description',
            ],
            'product.after_price',
            function (array $context) {
                $product = $context['product'] ?? null;
                if (is_array($product) && isset($product['id'])) {
                    $product = Product::query()->find($product['id']);
                }

                return $product instanceof Product
                    ? $this->app->make(TieredPricingResolver::class)->resolveForProduct($product)
                    : null;
            }
        );

        // 2. Register pricing step for automatic volume line adjustments
        $pricer->addStep($this->app->make(TieredPricingStep::class), CartPricer::LINE_ADJUSTMENTS);
    }
}
