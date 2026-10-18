<?php

namespace EcoModular\FrequentlyBoughtTogether;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use Illuminate\Support\ServiceProvider;

/**
 * Places the "frequently bought together" bundle on product pages through the
 * "product.after_details" slot. Which products appear is decided from order
 * history by BundleRecommender; the merchant's options live in module.json.
 */
class FrequentlyBoughtTogetherServiceProvider extends ServiceProvider
{
    public const MODULE = 'ecomodular/frequently-bought-together';

    public function boot(StorefrontSlots $slots, ModuleSettingsSchema $settings): void
    {
        $slots->block(self::MODULE, 'bundle', 'Frequently bought together', 'BundleWidget', ['product.after_details', 'product.after_add_to_cart'], 'product.after_details', function (array $context) use ($settings) {
            if (! (bool) $settings->of(self::MODULE, 'enabled', true)) {
                return null;
            }

            $product = $context['product'] ?? null;

            return $product instanceof Product ? $this->app->make(BundleRecommender::class)->props($product) : null;
        });
    }
}
