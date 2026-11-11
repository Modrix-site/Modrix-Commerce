<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Themes\CustomizerTabs;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\ThemeDemoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class CircuitThemeTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        $this->setUpThemes(withBundled: true);
        config(['inertia.ssr.enabled' => false]);
        $this->enableCheckout();
    }

    private function circuit(): Extension
    {
        app(ThemeRepository::class)->sync();

        return Extension::where('name', 'modrixcommerce/circuit')->firstOrFail();
    }

    public function test_package_is_valid_and_every_setting_is_on_a_tab(): void
    {
        $theme = $this->circuit();
        $this->assertNotSame(ModuleState::Invalid, $theme->state, implode(' ', (array) $theme->errors));
        $fields = $theme->manifest['customizer']['fields'];
        $this->assertCount(9, $fields);
        foreach ($fields as $key => $field) {
            $this->assertArrayHasKey($field['tab'] ?? '', CustomizerTabs::TABS, "{$key} has no known tab");
        }
        $this->assertCount(13, $theme->manifest['sections']['home']);
        $this->assertSame('src/product.tsx', $theme->manifest['frontend']['product']);
        $this->assertSame('Instrument Sans', $theme->manifest['fonts'][0]['family']);
    }

    public function test_homepage_resolves_campaigns_tiles_and_assets(): void
    {
        app(ThemeManager::class)->activate('modrixcommerce/circuit');
        $this->sellable('20.00', 5);

        // The homepage is built from templates/index.json (section capability).
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.name', 'modrixcommerce/circuit')
            ->where('storefront.theme.settings.sticky_header', false));

        $this->get('/theme-assets/modrixcommerce/circuit/assets/hero-watch.jpg')->assertOk();
        $this->get('/theme-assets/modrixcommerce/circuit/assets/instrument-sans.woff2')->assertOk();
    }

    public function test_demo_import_adds_products_with_sale_prices_once(): void
    {
        $manifest = ThemeManifest::fromArray($this->circuit()->manifest);
        $importer = app(ThemeDemoImporter::class);

        $this->assertSame(['categories' => 6, 'products' => 8], $importer->import($manifest, $this->owner()));
        $this->assertSame(['categories' => 0, 'products' => 0], $importer->import($manifest, $this->owner()));

        $watch = Product::where('slug', 'circuit-demo-1')->firstOrFail();
        $this->assertTrue(Category::where('slug', 'circuit-audio')->firstOrFail()->products()->exists());
        $this->assertNotNull($watch->images()->first());
        // Shoppers see the sale: price and the struck-through compare-at price.
        $this->get('/search?q=Smart+Watch')->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.slug', 'circuit-demo-1')
            ->where('products.data.0.price.amount', '129.00')
            ->where('products.data.0.compare_at_price.amount', '149.00'));
    }

    public function test_catalog_product_and_cart_render_with_their_places(): void
    {
        app(ThemeManager::class)->activate('modrixcommerce/circuit');
        $category = Category::factory()->create(['slug' => 'circuit-test']);
        $variant = $this->sellable('12.50', 5, product: ['name' => 'Circuit speaker']);
        $variant->product->categories()->attach($category);

        $this->get('/c/circuit-test')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.name', 'modrixcommerce/circuit')
            ->where('products.data.0.id', $variant->product_id));
        $this->get('/p/'.$variant->product->slug)->assertInertia(fn (Assert $page) => $page
            ->where('product.id', $variant->product_id)
            ->has('placeSlots'));
        $this->addToCart($variant, 2);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('cart.item_count', 2));
    }
}
