<?php

namespace EcoModular\Sitemap\Http;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Seo\Sitemap;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Content\Models\Page;
use App\Http\Controllers\Controller;
use EcoModular\Sitemap\SitemapServiceProvider as Module;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin: the sitemap status page (`can:sitemap.manage`) — what the file
 * contains, where it lives and a button to rebuild it.
 */
class SitemapAdminController extends Controller
{
    public function index(ModuleSettingsSchema $settings): Response
    {
        $enabled = (bool) $settings->of(Module::MODULE, 'enabled', true);
        $withProducts = (bool) $settings->of(Module::MODULE, 'include_products', true);
        $withCategories = (bool) $settings->of(Module::MODULE, 'include_categories', true);
        $withBrands = (bool) $settings->of(Module::MODULE, 'include_brands', true);
        $withPages = (bool) $settings->of(Module::MODULE, 'include_pages', true);

        $counts = [
            'home' => 1,
            'products' => $withProducts ? Product::query()->visible()->count() : 0,
            'categories' => $withCategories ? Category::query()->active()->count() : 0,
            'brands' => $withBrands ? Brand::query()->count() : 0,
            'pages' => $withPages ? Page::query()->where('status', 'published')->count() : 0,
        ];
        $extension = Extension::query()->where('name', Module::MODULE)->first();

        return Inertia::render('module/modrixcommerce/sitemap/admin/index', [
            'state' => [
                'enabled' => $enabled,
                'url' => route('sitemap'),
                'counts' => $counts,
                'total' => array_sum($counts),
                'include_images' => (bool) $settings->of(Module::MODULE, 'include_images', false),
                'changefreq' => (string) $settings->of(Module::MODULE, 'changefreq', 'weekly'),
            ],
            'settingsUrl' => $extension !== null ? route('admin.extensions.show', $extension) : route('admin.extensions.index'),
            'regenerateUrl' => route('admin.modrixcommerce.sitemap.regenerate'),
        ]);
    }

    public function regenerate(): RedirectResponse
    {
        Sitemap::flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The sitemap will be rebuilt on the next visit to '.route('sitemap').'.']);

        return back();
    }
}
