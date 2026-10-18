<?php

namespace EcoModular\Sitemap;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Seo\Sitemap;
use App\Core\Seo\SitemapEntry;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Content\Models\Page;
use Carbon\CarbonInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Contributes the storefront's XML sitemap. The core serves it at /sitemap.xml
 * and caches it for an hour; this provider supplies the rows the merchant
 * chose in the settings. Nothing is generated until that endpoint is called.
 */
class SitemapServiceProvider extends ServiceProvider
{
    public const MODULE = 'ecomodular/sitemap';

    public function boot(Sitemap $sitemap, ModuleSettingsSchema $settings): void
    {
        if (! (bool) $settings->of(self::MODULE, 'enabled', true)) {
            return;
        }

        $sitemap->register(self::MODULE, fn () => $this->entries($settings));
    }

    /**
     * @return \Generator<int, SitemapEntry>
     */
    private function entries(ModuleSettingsSchema $settings): \Generator
    {
        $changefreq = $this->changefreq((string) $settings->of(self::MODULE, 'changefreq', 'weekly'));
        $images = (bool) $settings->of(self::MODULE, 'include_images', false);

        // Home page, dated by the newest catalogue change.
        yield SitemapEntry::make(
            route('home'),
            $this->latestUpdate(),
            $changefreq === 'monthly' ? 'weekly' : 'daily',
            1.0,
        );

        if ((bool) $settings->of(self::MODULE, 'include_products', true)) {
            foreach (Product::query()->visible()->orderBy('id')->cursor() as $product) {
                /** @var Product $product */
                yield SitemapEntry::make(
                    route('storefront.product', $product->slug),
                    $product->updated_at,
                    $changefreq,
                    0.8,
                    $images ? $this->productImages($product) : [],
                );
            }
        }

        if ((bool) $settings->of(self::MODULE, 'include_categories', true)) {
            foreach (Category::query()->active()->orderBy('id')->cursor() as $category) {
                /** @var Category $category */
                yield SitemapEntry::make(route('storefront.category', $category->slug), $category->updated_at, $changefreq, 0.7);
            }
        }

        if ((bool) $settings->of(self::MODULE, 'include_brands', true)) {
            foreach (Brand::query()->orderBy('id')->cursor() as $brand) {
                /** @var Brand $brand */
                yield SitemapEntry::make(route('storefront.brand', $brand->slug), $brand->updated_at, $changefreq, 0.6);
            }
        }

        if ((bool) $settings->of(self::MODULE, 'include_pages', true)) {
            foreach (Page::query()->where('status', 'published')->orderBy('id')->cursor() as $page) {
                /** @var Page $page */
                yield SitemapEntry::make(route('storefront.page', $page->slug), $page->updated_at, $changefreq, 0.5);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function productImages(Product $product): array
    {
        $urls = [];

        foreach ($product->images()->get() as $image) {
            /** @var ProductImage $image */
            $urls[] = $this->absolute($image->url());
        }

        return $urls;
    }

    private function absolute(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    private function latestUpdate(): CarbonInterface
    {
        $updated = Product::query()->visible()->latest('updated_at')->value('updated_at');

        return $updated instanceof CarbonInterface ? $updated : now();
    }

    private function changefreq(string $value): string
    {
        return in_array($value, ['daily', 'weekly', 'monthly'], true) ? $value : 'weekly';
    }
}
