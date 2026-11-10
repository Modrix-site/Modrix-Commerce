<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\ModuleState;
use App\Core\Themes\Sections\ThemeTemplates;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductAttribute;
use App\Domains\Catalog\ThemeDemoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class SableThemeTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpThemes();
        File::copyDirectory(base_path('themes/modrixcommerce/sable'), $this->themesPath.'/modrixcommerce/sable');
        app(ThemeRepository::class)->sync();
        app(ThemeManager::class)->activate('modrixcommerce/sable');
        $this->enableCheckout();
    }

    public function test_package_and_all_native_editor_templates_are_valid(): void
    {
        $theme = app(ThemeRepository::class)->find('modrixcommerce/sable');
        $this->assertSame(ModuleState::Enabled, $theme->state, implode(' ', (array) $theme->errors));
        $manifest = ThemeManifest::fromArray($theme->manifest);
        $this->assertTrue(ThemeTemplates::supports($manifest));
        foreach (ThemeTemplates::keys() as $key) {
            $this->assertNotEmpty(app(ThemeTemplates::class)->saved($manifest, $key)['order']);
        }
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.themeSections.template', 'index')
            ->where('storefront.themeSections.header.0.settings.brand', 'SABLE ATELIER')
            ->where('storefront.themeSections.main.0.type', 'image-banner')
            ->where('storefront.themeSections.main.0.settings.image', fn ($url) => str_contains($url, '/theme-assets/modrixcommerce/sable/assets/hero.jpg'))
            ->has('storefront.themeSections.main', 11));
        $this->get('/theme-assets/modrixcommerce/sable/assets/hero.jpg')->assertOk();
        $this->actingAs($this->owner())->get('/admin/themes')->assertInertia(fn (Assert $page) => $page
            ->where('themes.0.screenshot', fn ($url) => str_contains($url, '/assets/demo-preview.webp')));
        $this->get('/theme-assets/modrixcommerce/sable/assets/demo-preview.webp')->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_editor_can_preview_a_draft_then_save_reordered_sections(): void
    {
        $theme = app(ThemeRepository::class)->find('modrixcommerce/sable');
        $manifest = ThemeManifest::fromArray($theme->manifest);
        $template = app(ThemeTemplates::class)->saved($manifest, 'template:index');
        $template['sections']['hero']['settings']['heading'] = 'A new horizon';
        $this->actingAs($this->owner())->get(route('admin.themes.editor', $theme))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/themes/editor')->has('schemas.image-banner')->has('templates.template:product'));
        $this->putJson(route('admin.themes.editor.draft', $theme), ['templates' => ['template:index' => $template]])->assertOk();
        $this->get('/?_editor=1')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.settings.heading', 'A new horizon'));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.settings.heading', 'A world beyond the everyday.'));
        [$template['order'][0], $template['order'][1]] = [$template['order'][1], $template['order'][0]];
        $this->putJson(route('admin.themes.editor.save', $theme), ['templates' => ['template:index' => $template]])->assertOk();
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.themeSections.main.0.type', 'category-circles')
            ->where('storefront.themeSections.main.1.settings.heading', 'A new horizon'));
    }

    public function test_demo_products_import_once_and_support_product_and_bag_routes(): void
    {
        $manifest = app(ThemeManager::class)->renderedManifest(app(ThemeRepository::class)->find('modrixcommerce/sable'));
        $importer = app(ThemeDemoImporter::class);
        $this->assertSame(['categories' => 3, 'products' => 12], $importer->import($manifest, $this->owner()));
        $this->assertSame(['categories' => 0, 'products' => 0], $importer->import($manifest, $this->owner()));
        $product = Product::where('slug', 'sable-demo-1')->firstOrFail();
        $this->get('/c/sable-travel')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.type', 'collection-banner')->has('storefront.themeSections.main.0.blocks', 4)->where('storefront.themeSections.main.1.type', 'main-collection')->has('products.data', 4));
        $this->get('/p/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.type', 'main-product')->where('product.id', $product->id));
        $this->addToCart($product->variants()->firstOrFail(), 2);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('storefront.themeSections.main.0.type', 'main-cart')->where('cart.item_count', 2));
    }

    public function test_catalog_exposes_real_deduplicated_safe_color_swatches(): void
    {
        $variant = $this->sellable();
        $attribute = ProductAttribute::create(['name' => 'Color', 'slug' => 'color', 'display_type' => 'swatches']);
        $color = $attribute->values()->create(['value' => 'Olive', 'slug' => 'olive', 'color' => '#61634a']);
        $variant->options()->attach($color);
        $this->get('/search')->assertInertia(fn (Assert $page) => $page
            ->has('products.data.0.swatches', 1)
            ->where('products.data.0.swatches.0.value', 'Olive')
            ->where('products.data.0.swatches.0.color', '#61634a'));
    }
}
