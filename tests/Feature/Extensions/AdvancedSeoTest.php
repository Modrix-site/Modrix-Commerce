<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class AdvancedSeoTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/advanced-seo';

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
        $this->installPlatform();
        $this->setUpModules();
        $this->copyModules();
        app(SettingsRepository::class)->set('store.name', 'Jhay Store');
    }

    public function test_titles_and_descriptions_apply_on_the_home_page_and_products(): void
    {
        $this->enableModule();
        $variant = $this->sellable('19.00', 4, [], [
            'name' => 'Cosy Cat Blanket',
            'slug' => 'cosy-cat-blanket',
            'short_description' => 'A soft blanket for a favourite companion.',
            'meta_title' => null,
            'meta_description' => null,
        ]);
        $written = Product::factory()->create([
            'name' => 'Heart Pattern Pet Bed',
            'slug' => 'heart-pattern-pet-bed',
            'meta_title' => 'Heart bed for pets',
            'meta_description' => 'A written description for search results.',
        ]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('storefront.seoTitle', 'Jhay Store')
            ->where('storefront.overlays', fn ($overlays) => collect($overlays)->contains(
                fn ($overlay) => ($overlay['component'] ?? null) === self::MODULE.'/SeoTags'
                    && ($overlay['props']['title'] ?? null) === 'Jhay Store'
                    && ($overlay['props']['robots'] ?? null) === 'index, follow',
            )));

        $this->get('/p/'.$variant->product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.meta_title', 'Cosy Cat Blanket | Jhay Store')
            ->where('product.meta_description', 'A soft blanket for a favourite companion.')
            ->where('storefront.seoTitle', 'Cosy Cat Blanket | Jhay Store'));

        $this->get('/p/'.$written->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.meta_title', 'Heart bed for pets')
            ->where('product.meta_description', 'A written description for search results.'));
    }

    public function test_product_pages_with_several_variants_load_prices_without_lazy_loading(): void
    {
        $this->enableModule();
        $first = $this->sellable('19.00', 4, [], ['slug' => 'many-sizes', 'meta_title' => null]);
        // Lazy loading is only reported when several variants were loaded together.
        $second = ProductVariant::factory()->create(['product_id' => $first->product_id, 'position' => 1]);
        $second->prices()->create(['currency' => 'USD', 'amount' => '25.0000']);

        $this->get('/p/many-sizes')->assertOk();
    }

    public function test_page_text_with_dollar_amounts_and_backslashes_is_not_corrupted(): void
    {
        $this->enableModule();
        $this->sellable('19.00', 4, [], [
            'name' => 'Lamp from $175 \\1 off',
            'slug' => 'priced-lamp',
            'meta_title' => null,
            'meta_description' => null,
        ]);

        // The first (HTML) visit carries the page JSON the module rewrites.
        $html = $this->get('/p/priced-lamp')->assertOk()->getContent();

        $this->assertStringContainsString('Lamp from $175 \\\\1 off', (string) $html);
    }

    public function test_the_browser_tab_uses_the_store_name_instead_of_the_app_name(): void
    {
        $this->enableModule();
        $this->withoutVite();

        $this->actingAs($this->owner())
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee('<title>Jhay Store</title>', false);
    }

    public function test_sitemap_submission_uses_the_search_console_api(): void
    {
        $this->enableModule();
        $config = tempnam(sys_get_temp_dir(), 'openssl');
        file_put_contents((string) $config, "openssl_conf = openssl_init\n[openssl_init]\n");
        $key = openssl_pkey_new([
            'private_key_bits' => 512,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $config,
        ]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $private, null, ['config' => $config]);
        $settings = app(SettingsRepository::class);
        $settings->set('extension.modrixcommerce.advanced-seo.google_credentials', json_encode([
            'client_email' => 'seo@example.iam.gserviceaccount.com',
            'private_key' => $private,
        ]));
        $settings->set('extension.modrixcommerce.advanced-seo.search_console_site', 'https://shop.example/');
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'www.googleapis.com/*' => Http::response('', 204),
        ]);

        $this->actingAs($this->owner())
            ->post(route('admin.modrixcommerce.advanced-seo.sitemap'))
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), 'webmasters/v3/sites/'.rawurlencode('https://shop.example/').'/sitemaps/'));
    }

    private function enableModule(): void
    {
        $modules = app(ModuleManager::class);
        $modules->install(self::MODULE, $this->owner());
        $modules->enable(self::MODULE, true, $this->owner());
    }

    private function copyModules(): void
    {
        File::copyDirectory(base_path('modules'), $this->modulesPath);
    }
}
