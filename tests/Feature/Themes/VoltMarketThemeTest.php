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

class VoltMarketThemeTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpThemes();
        File::copyDirectory(base_path('themes/modrixcommerce/voltmarket'), $this->themesPath.'/modrixcommerce/voltmarket');
        app(ThemeRepository::class)->sync();
        app(ThemeManager::class)->activate('modrixcommerce/voltmarket');
        $this->enableCheckout();
    }

    public function test_package_and_all_native_editor_templates_are_valid(): void
    {
        $theme = app(ThemeRepository::class)->find('modrixcommerce/voltmarket');
        $this->assertSame(ModuleState::Enabled, $theme->state, implode(' ', (array) $theme->errors));
        $manifest = ThemeManifest::fromArray($theme->manifest);
        $this->assertTrue(ThemeTemplates::supports($manifest));
        foreach (ThemeTemplates::keys() as $key) {
            $this->assertNotEmpty(app(ThemeTemplates::class)->saved($manifest, $key)['order']);
        }
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.themeSections.template', 'index')
            ->where('storefront.themeSections.header.0.settings.sticky', true)
            ->where('storefront.themeSections.header.0.settings.brand', 'voltmarket')
            ->where('storefront.themeSections.header.0.settings.show_mobile_bar', true)
            ->where('storefront.themeSections.header.0.settings.mobile_categories_label', 'Categories')
            ->where('storefront.themeSections.main.0.type', 'hero')
            ->where('storefront.themeSections.main.0.settings.image', fn ($url) => str_contains($url, '/theme-assets/modrixcommerce/voltmarket/assets/tile-kitchen.jpg'))
            ->has('storefront.themeSections.main', 11));
        $this->get('/theme-assets/modrixcommerce/voltmarket/assets/tile-kitchen.jpg')->assertOk();
        $this->actingAs($this->owner())->get('/admin/themes')->assertInertia(fn (Assert $page) => $page
            ->where('themes.0.screenshot', fn ($url) => str_contains($url, '/assets/demo-preview.webp')));
        $this->get('/theme-assets/modrixcommerce/voltmarket/assets/demo-preview.webp')->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_editor_can_preview_a_draft_then_save_reordered_sections(): void
    {
        $theme = app(ThemeRepository::class)->find('modrixcommerce/voltmarket');
        $manifest = ThemeManifest::fromArray($theme->manifest);
        $template = app(ThemeTemplates::class)->saved($manifest, 'template:index');
        $template['sections']['hero']['settings']['heading'] = 'A new horizon';
        $this->actingAs($this->owner())->get(route('admin.themes.editor', $theme))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/themes/editor')->has('schemas.hero')->has('templates.template:product'));
        $this->putJson(route('admin.themes.editor.draft', $theme), ['templates' => ['template:index' => $template]])->assertOk();
        $this->get('/?_editor=1')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.settings.heading', 'A new horizon'));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.settings.heading', 'Small changes. Brilliant living.'));
        [$template['order'][0], $template['order'][1]] = [$template['order'][1], $template['order'][0]];
        $this->putJson(route('admin.themes.editor.save', $theme), ['templates' => ['template:index' => $template]])->assertOk();
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.themeSections.main.0.type', 'benefits')
            ->where('storefront.themeSections.main.1.settings.heading', 'A new horizon'));
    }

    public function test_demo_products_import_once_and_support_product_and_bag_routes(): void
    {
        $manifest = app(ThemeManager::class)->renderedManifest(app(ThemeRepository::class)->find('modrixcommerce/voltmarket'));
        $importer = app(ThemeDemoImporter::class);
        $this->assertSame(['categories' => 7, 'products' => 10], $importer->import($manifest, $this->owner()));
        $this->assertSame(['categories' => 0, 'products' => 0], $importer->import($manifest, $this->owner()));
        $product = Product::where('slug', 'voltmarket-demo-1')->firstOrFail();
        $this->get('/c/voltmarket-audio')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.type', 'main-collection')->has('products.data', 3));
        $this->get('/p/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.type', 'main-product')->where('product.id', $product->id));
        $this->addToCart($product->variants()->firstOrFail(), 2);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.type', 'main-cart')->where('cart.item_count', 2));
    }


    public function test_sticky_header_can_be_toggled_in_the_live_editor(): void
    {
        $theme = app(ThemeRepository::class)->find('modrixcommerce/voltmarket');
        $manifest = ThemeManifest::fromArray($theme->manifest);
        $header = app(ThemeTemplates::class)->saved($manifest, 'group:header');
        $this->actingAs($this->owner());
        foreach ([false, true] as $sticky) {
            $header['sections']['header']['settings']['sticky'] = $sticky;
            $this->putJson(route('admin.themes.editor.save', $theme), ['templates' => ['group:header' => $header]])->assertOk();
            $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.header.0.settings.sticky', $sticky));
        }
    }
}
