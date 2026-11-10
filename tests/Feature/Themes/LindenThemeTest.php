<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\ModuleState;
use App\Core\Themes\Sections\ThemeTemplates;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\ThemeDemoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class LindenThemeTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpThemes();
        File::copyDirectory(base_path('themes/modrixcommerce/linden'), $this->themesPath.'/modrixcommerce/linden');
        app(ThemeRepository::class)->sync();
        app(ThemeManager::class)->activate('modrixcommerce/linden');
        $this->enableCheckout();
    }

    public function test_package_has_all_live_editor_templates_and_local_assets(): void
    {
        $theme = app(ThemeRepository::class)->find('modrixcommerce/linden');
        $this->assertSame(ModuleState::Enabled, $theme->state, implode(' ', (array) $theme->errors));
        $manifest = ThemeManifest::fromArray($theme->manifest);
        foreach (ThemeTemplates::keys() as $key) {
            $this->assertNotEmpty(app(ThemeTemplates::class)->saved($manifest, $key)['order']);
        }
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.themeSections.template', 'index')
            ->where('storefront.themeSections.header.0.settings.sticky', true)
            ->where('storefront.themeSections.header.0.settings.brand', 'Linden & Vale')
            ->where('storefront.themeSections.main.0.type', 'hero')
            ->where('storefront.themeSections.main.0.settings.image', fn ($url) => str_contains($url, '/theme-assets/modrixcommerce/linden/assets/hero.webp'))
            ->has('storefront.themeSections.main', 10)
            ->has('storefront.themeSections.footer', 1));
        $this->get('/theme-assets/modrixcommerce/linden/assets/hero.webp')->assertOk();
        $this->get('/theme-assets/modrixcommerce/linden/assets/work-sans.woff2')->assertOk();
        $this->assertArrayHasKey('custom.body_size', $manifest->customizerFields());
    }

    public function test_latest_editor_previews_saves_and_reorders_homepage_sections(): void
    {
        $theme = app(ThemeRepository::class)->find('modrixcommerce/linden');
        $manifest = ThemeManifest::fromArray($theme->manifest);
        $template = app(ThemeTemplates::class)->saved($manifest, 'template:index');
        $template['sections']['hero']['settings']['heading'] = 'Your everyday ritual';
        $template['sections']['hero']['settings']['heading_size'] = 38;
        $this->actingAs($this->owner())->get(route('admin.themes.editor', $theme))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/themes/editor')->has('schemas.hero')->has('schemas.product-rail')->has('templates.template:product'));
        $this->putJson(route('admin.themes.editor.draft', $theme), ['templates' => ['template:index' => $template]])->assertOk();
        $this->get('/?_editor=1')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.settings.heading', 'Your everyday ritual')->where('storefront.themeSections.main.0.settings.heading_size', 38));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.settings.heading', fn ($heading) => $heading !== 'Your everyday ritual'));
        [$template['order'][0], $template['order'][1]] = [$template['order'][1], $template['order'][0]];
        $this->putJson(route('admin.themes.editor.save', $theme), ['templates' => ['template:index' => $template]])->assertOk();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.type', 'ranges')->where('storefront.themeSections.main.1.settings.heading', 'Your everyday ritual'));
    }

    public function test_demo_import_is_idempotent_and_uses_real_catalog_cart_and_checkout(): void
    {
        $manifest = app(ThemeManager::class)->renderedManifest(app(ThemeRepository::class)->find('modrixcommerce/linden'));
        $this->assertSame(['categories' => 13, 'products' => 32, 'pages' => 3], app(ThemeDemoImporter::class)->import($manifest, $this->owner()));
        $this->assertSame(['categories' => 0, 'products' => 0, 'pages' => 0], app(ThemeDemoImporter::class)->import($manifest, $this->owner()));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('storefront.themeSections.main.2.settings.collection.products', 4));
        $this->get('/c/linden-body')->assertInertia(fn (Assert $page) => $page->has('products.data', 24)->where('products.total', 27)->where('storefront.themeSections.main.0.type', 'main-collection'));
        $product = Product::where('slug', 'linden-moringa-cleanser')->firstOrFail();
        $this->get('/p/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('product.id', $product->id)->where('storefront.themeSections.main.0.type', 'main-product'));
        $variant = $product->variants()->firstOrFail();
        $this->addToCart($variant, 2);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('cart.item_count', 2)->where('storefront.themeSections.main.0.type', 'main-cart'));
        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1, 'buy_now' => true])->assertRedirect(route('checkout.show'));
    }

    public function test_sale_filter_only_includes_active_discounted_variants_in_store_currency(): void
    {
        $regular = $this->sellable('20.00');
        $discount = $this->sellable('15.00');
        $discount->prices()->update(['compare_at_amount' => '20.00']);
        $inactive = $this->sellable('25.00');
        $other = $inactive->replicate();
        $other->sku = 'INACTIVE-SALE';
        $other->is_active = false;
        $other->save();
        $other->prices()->create(['currency' => 'USD', 'amount' => '10.00', 'compare_at_amount' => '30.00']);
        $wrongCurrency = $this->sellable('30.00');
        $wrongCurrency->prices()->create(['currency' => 'AED', 'amount' => '20.00', 'compare_at_amount' => '35.00']);
        $this->get('/search?on_sale=1')->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $discount->product_id));
        $this->get('/search')->assertInertia(fn (Assert $page) => $page->has('products.data', 4));
        $this->get('/search?on_sale=invalid')->assertSessionHasErrors('on_sale');
    }

    public function test_sticky_header_can_be_toggled_in_the_live_editor(): void
    {
        $theme = app(ThemeRepository::class)->find('modrixcommerce/linden');
        $manifest = ThemeManifest::fromArray($theme->manifest);
        $header = app(ThemeTemplates::class)->saved($manifest, 'group:header');
        $this->actingAs($this->owner());
        foreach ([false, true] as $sticky) {
            $header['sections']['header']['settings']['sticky'] = $sticky;
            $this->putJson(route('admin.themes.editor.save', $theme), ['templates' => ['group:header' => $header]])->assertOk();
            $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.header.0.settings.sticky', $sticky));
        }
    }

    public function test_product_rails_fall_back_to_latest_products_without_the_demo_categories(): void
    {
        // A store that never imported Relay's demo has no relay-* categories.
        $this->sellable('25.00', 5, product: ['name' => 'Store Phone']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.themeSections.main', function ($sections) {
                $rails = collect($sections)->where('type', 'product-rail');
                $this->assertNotEmpty($rails);

                return $rails->every(fn ($rail) => collect($rail['settings']['collection']['products'] ?? [])->pluck('name')->contains('Store Phone'));
            }));
    }
}
