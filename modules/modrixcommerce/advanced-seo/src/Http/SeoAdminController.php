<?php

namespace EcoModular\AdvancedSeo\Http;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Seo\Sitemap;
use App\Domains\Catalog\Models\Product;
use EcoModular\AdvancedSeo\GoogleSearchConsole;
use EcoModular\AdvancedSeo\SeoDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route as RouteFacade;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Admin status for titles, descriptions and Google submission.
 */
class SeoAdminController
{
    public function index(ModuleSettingsSchema $settings, GoogleSearchConsole $google): Response
    {
        $extension = Extension::query()->where('name', SeoDocument::MODULE)->first();
        $credentials = trim((string) $settings->of(SeoDocument::MODULE, 'google_credentials', ''));
        $site = trim((string) $settings->of(SeoDocument::MODULE, 'search_console_site', ''));
        $sample = Product::query()->visible()->latest('updated_at')->first();

        return Inertia::render('module/modrixcommerce/advanced-seo/admin/index', [
            'state' => [
                'enabled' => (bool) $settings->of(SeoDocument::MODULE, 'enabled', true),
                'homeTitle' => (string) $settings->of(SeoDocument::MODULE, 'home_title', '{store}'),
                'productTitle' => (string) $settings->of(SeoDocument::MODULE, 'product_title', '{name} | {store}'),
                'sampleProduct' => $sample instanceof Product ? $sample->name : null,
                'sitemapUrl' => RouteFacade::has('sitemap') ? route('sitemap') : url('/sitemap.xml'),
                'siteUrl' => $site,
                'serviceAccount' => $credentials === '' ? null : $google->accountEmail($credentials),
                'verification' => trim((string) $settings->of(SeoDocument::MODULE, 'google_verification', '')) !== '',
                'productCount' => Product::query()->visible()->count(),
            ],
            'settingsUrl' => $extension !== null ? route('admin.extensions.show', $extension) : route('admin.extensions.index'),
            'submitUrl' => route('admin.modrixcommerce.advanced-seo.sitemap'),
            'crawlUrl' => route('admin.modrixcommerce.advanced-seo.crawl'),
        ]);
    }

    public function submitSitemap(ModuleSettingsSchema $settings, GoogleSearchConsole $google): RedirectResponse
    {
        try {
            $google->submitSitemap($this->credentials($settings), $this->site($settings), route('sitemap'));
            Sitemap::flush();
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The sitemap was submitted to Google Search Console.']);

        return back();
    }

    public function requestCrawl(ModuleSettingsSchema $settings, GoogleSearchConsole $google): RedirectResponse
    {
        $urls = [route('home')];

        foreach (Product::query()->visible()->latest('updated_at')->limit(50)->pluck('slug') as $slug) {
            $urls[] = route('storefront.product', $slug);
        }

        try {
            $result = $google->requestCrawl($this->credentials($settings), $urls);
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        $message = 'Asked Google to crawl '.$result['submitted'].' URL'.($result['submitted'] === 1 ? '' : 's').'.';

        if ($result['failed'] > 0) {
            $message .= ' '.$result['failed'].' were not accepted.';
        }

        if (Product::query()->visible()->count() > 50) {
            $message .= ' The newest 50 products were included.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function credentials(ModuleSettingsSchema $settings): string
    {
        $credentials = trim((string) $settings->of(SeoDocument::MODULE, 'google_credentials', ''));

        if ($credentials === '') {
            throw new RuntimeException('Add the Google service account JSON in the SEO settings first.');
        }

        return $credentials;
    }

    private function site(ModuleSettingsSchema $settings): string
    {
        $site = trim((string) $settings->of(SeoDocument::MODULE, 'search_console_site', ''));

        return $site !== '' ? $site : url('/');
    }
}
