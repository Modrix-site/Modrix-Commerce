<?php

namespace EcoModular\BuyTogetherSave;

use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use App\Domains\Checkout\Pricing\CartPricer;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Buy Together & Save module:
 * - Injects the promotional bundle block into product pages via StorefrontSlots.
 * - Injects the pricing step into CartPricer to calculate and apply bundle savings.
 */
class BuyTogetherSaveServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/buy-together-save';

    public function register(): void
    {
        $this->app->singleton(BuyTogetherSettings::class);
        $this->app->singleton(BuyTogetherDealResolver::class);
        $this->app->singleton(BuyTogetherPricingStep::class);
    }

    public function boot(StorefrontSlots $slots, CartPricer $pricer): void
    {
        // 1. Register customizable product page block
        $slots->block(
            self::MODULE,
            'buy_together_save',
            'Buy Together & Save',
            'BuyTogetherSaveWidget',
            [
                'product.after_details',
                'product.after_add_to_cart',
                'product.after_price',
                'product.after_description',
            ],
            'product.after_details',
            function (array $context) {
                $product = $context['product'] ?? null;
                if (is_array($product) && isset($product['id'])) {
                    $product = Product::query()->find($product['id']);
                }

                return $product instanceof Product
                    ? $this->app->make(BuyTogetherDealResolver::class)->resolveForProduct($product)
                    : null;
            }
        );

        // 2. Register pricing step for automatic line discounts
        $pricer->addStep($this->app->make(BuyTogetherPricingStep::class), CartPricer::LINE_ADJUSTMENTS);
    }
}
