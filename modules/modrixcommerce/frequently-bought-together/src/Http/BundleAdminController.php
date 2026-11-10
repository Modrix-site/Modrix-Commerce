<?php

namespace EcoModular\FrequentlyBoughtTogether\Http;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\StorefrontCatalog;
use App\Http\Controllers\Controller;
use EcoModular\FrequentlyBoughtTogether\BundleRecommender;
use EcoModular\FrequentlyBoughtTogether\FrequentlyBoughtTogetherServiceProvider as Module;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin: a read-only view of the feature (`can:bundles.manage`) — how it is
 * configured, and a live sample of what a shopper sees on one product page.
 */
class BundleAdminController extends Controller
{
    public function index(ModuleSettingsSchema $settings, StorefrontCatalog $catalog, BundleRecommender $recommender, StoreSettings $store): Response
    {
        $enabled = (bool) $settings->of(Module::MODULE, 'enabled', true);
        $sample = $this->sampleProduct($catalog, $store->currency());
        $preview = $sample instanceof Product && $enabled ? $recommender->props($sample) : null;
        $extension = Extension::query()->where('name', Module::MODULE)->first();

        return Inertia::render('module/modrixcommerce/frequently-bought-together/admin/index', [
            'state' => [
                'enabled' => $enabled,
                'heading' => (string) $settings->of(Module::MODULE, 'heading', 'Frequently bought together'),
                'max_products' => max(1, min(4, (int) $settings->of(Module::MODULE, 'max_products', 3))),
                'min_orders' => max(1, min(20, (int) $settings->of(Module::MODULE, 'min_orders', 1))),
                'fallback_related' => (bool) $settings->of(Module::MODULE, 'fallback_related', true),
                'show_savings' => (bool) $settings->of(Module::MODULE, 'show_savings', true),
                'products' => Product::query()->visible()->count(),
            ],
            'sample' => $sample instanceof Product ? [
                'name' => $sample->localized('name'),
                'url' => route('storefront.product', $sample->slug),
            ] : null,
            'preview' => $preview === null ? null : [
                'heading' => $preview['heading'],
                'current' => ['name' => $preview['current']['name']],
                'items' => array_map(fn (array $item) => [
                    'name' => $item['name'],
                    'url' => $item['url'],
                ], $preview['items']),
            ],
            'settingsUrl' => $extension !== null ? route('admin.extensions.show', $extension) : route('admin.extensions.index'),
        ]);
    }

    private function sampleProduct(StorefrontCatalog $catalog, string $currency): ?Product
    {
        $card = $catalog->latest($currency, 1)[0] ?? null;

        if ($card === null) {
            return null;
        }

        return $catalog->findVisible((string) $card['slug']);
    }
}
