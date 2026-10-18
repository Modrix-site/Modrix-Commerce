<?php

namespace EcoModular\ProductLabel\Http;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\StorefrontCatalog;
use App\Http\Controllers\Controller;
use EcoModular\ProductLabel\ProductLabelServiceProvider as Module;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin: how the Product Label module is configured, and a live sample of the
 * badges shoppers see (`can:labels.manage`).
 */
class LabelAdminController extends Controller
{
    public function index(ModuleSettingsSchema $settings, StorefrontCatalog $catalog, StoreSettings $store): Response
    {
        $enabled = (bool) $settings->of(Module::MODULE, 'enabled', true);
        $cards = $catalog->latest($store->currency(), 8);

        $preview = [];

        foreach ($cards as $card) {
            $labels = $card['labels'] ?? [];

            if (! $enabled || $labels === []) {
                continue;
            }

            $preview[] = [
                'name' => $card['name'],
                'image' => $card['image'],
                'labels' => array_map(fn (array $label) => [
                    'text' => $label['text'],
                    'tone' => $label['tone'],
                ], $labels),
            ];

            if (count($preview) >= 3) {
                break;
            }
        }

        $extension = Extension::query()->where('name', Module::MODULE)->first();

        return Inertia::render('module/ecomodular/product-label/admin/index', [
            'state' => [
                'enabled' => $enabled,
                'style' => (string) $settings->of(Module::MODULE, 'badge_style', 'solid'),
                'position' => (string) $settings->of(Module::MODULE, 'position', 'top-left'),
                'max_labels' => max(1, min(4, (int) $settings->of(Module::MODULE, 'max_labels', 2))),
                'rules' => [
                    'new' => (bool) $settings->of(Module::MODULE, 'show_new', true),
                    'new_days' => max(1, min(365, (int) $settings->of(Module::MODULE, 'new_days', 21))),
                    'sale' => (bool) $settings->of(Module::MODULE, 'show_sale', true),
                    'low_stock' => (bool) $settings->of(Module::MODULE, 'show_low_stock', true),
                    'low_stock_threshold' => max(1, min(100, (int) $settings->of(Module::MODULE, 'low_stock_threshold', 5))),
                    'featured' => (bool) $settings->of(Module::MODULE, 'show_featured', false),
                    'sold_out' => (bool) $settings->of(Module::MODULE, 'show_sold_out', true),
                ],
                'custom_labels' => $this->customCount((string) $settings->of(Module::MODULE, 'custom_labels', '')),
                'products' => Product::query()->visible()->count(),
            ],
            'preview' => $preview,
            'settingsUrl' => $extension !== null
                ? route('admin.extensions.show', $extension)
                : route('admin.extensions.index'),
        ]);
    }

    private function customCount(string $raw): int
    {
        $count = 0;

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            if (str_contains($line, '=') && trim(explode('=', $line, 2)[1]) !== '') {
                $count++;
            }
        }

        return $count;
    }
}
