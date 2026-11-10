<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\ThemeDemoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class NordThemeTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        $this->setUpThemes(withBundled: $this->name() !== 'test_package_installs_without_activation_or_demo_import');
        config(['inertia.ssr.enabled' => false]);
        $this->enableCheckout();
    }

    public function test_package_installs_without_activation_or_demo_import(): void
    {
        $file = new UploadedFile(base_path('dist/ecomodular-nord-1.0.0.zip'), 'nord.zip', 'application/zip', null, true);
        $this->actingAs($this->userWithRole('administrator'))
            ->post(route('admin.themes.packages.store'), ['package' => $file])->assertSessionHasNoErrors();
        $theme = Extension::where('name', 'modrixcommerce/nord')->firstOrFail();
        $this->assertSame(ModuleState::Discovered, $theme->state);
        $this->assertNull($theme->applied_manifest);
        $this->assertCount(105, $theme->manifest['customizer']['fields']);
        $this->assertSame('src/product.tsx', $theme->manifest['frontend']['product']);
        $this->assertDatabaseMissing('products', ['slug' => 'nord-demo-1']);
    }

    public function test_reference_assets_and_twelve_default_links_resolve_and_demo_import_is_additive(): void
    {
        app(ThemeRepository::class)->sync();
        app(ThemeManager::class)->activate('modrixcommerce/nord');
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('storefront.navigation.header.links', 12)
            ->where('storefront.theme.settings.hero_heading', 'Fall Winter Collection')
            ->where('storefront.theme.settings.hero_image', fn ($url) => str_contains($url, '/theme-assets/modrixcommerce/nord/assets/hero.jpg')));
        $this->get('/theme-assets/modrixcommerce/nord/assets/hero.jpg')->assertOk();
        $manifest = ThemeManifest::fromArray(Extension::where('name', 'modrixcommerce/nord')->firstOrFail()->manifest);
        $importer = app(ThemeDemoImporter::class);
        $this->assertSame(['categories' => 2, 'products' => 6], $importer->import($manifest, $this->owner()));
        $this->assertSame(['categories' => 0, 'products' => 0], $importer->import($manifest, $this->owner()));
    }

    public function test_catalog_slots_receive_category_brand_and_search_context_with_server_filters(): void
    {
        app(ThemeManager::class)->activate('modrixcommerce/nord');
        $category = Category::factory()->create(['slug' => 'nord-test-category']);
        $brand = Brand::query()->create(['name' => 'Nord Brand', 'slug' => 'nord-test-brand']);
        $variant = $this->sellable('25', 3, product: ['name' => 'Nord sweater', 'brand_id' => $brand->id]);
        $variant->product->categories()->attach($category);
        $this->sellable('50', 0);
        app(StorefrontSlots::class)->register('catalog.after_products', 'acme/future', 'CatalogPanel', fn ($context) => [
            'category' => $context['category']?->slug, 'brand' => $context['brand']?->slug, 'query' => $context['query'],
        ]);
        $this->get('/c/nord-test-category')->assertInertia(fn (Assert $page) => $page->where('slots.0.props.category', $category->slug));
        $this->get('/b/nord-test-brand')->assertInertia(fn (Assert $page) => $page->where('slots.0.props.brand', $brand->slug));
        $this->get('/search?q=Nord&in_stock=1&min_price=20&max_price=30&sort=price_desc')->assertInertia(fn (Assert $page) => $page
            ->where('slots.0.component', 'acme/future/CatalogPanel')->where('slots.0.props.query', 'Nord')
            ->where('products.total', 1)->where('products.data.0.id', $variant->product_id)->where('sort', 'price_desc'));
    }

    public function test_existing_and_future_slots_preserve_core_cart_and_checkout_totals(): void
    {
        app(ThemeManager::class)->activate('modrixcommerce/nord');
        $slots = app(StorefrontSlots::class);
        $slots->register('home.after_featured', 'acme/future', 'HomePanel', fn () => ['message' => 'Home extension']);
        $slots->register('product.after_details', 'acme/future', 'ProductPanel', fn ($context) => ['id' => $context['product']->id]);
        $slots->register('cart.after_totals', 'acme/future', 'CartPanel', fn ($context) => ['total' => $context['cart']->toArray()['grand_total']]);
        $slots->register('checkout.after_summary', 'acme/future', 'CheckoutPanel', fn ($context) => ['total' => $context['cart']->toArray()['grand_total']]);
        $slots->register('checkout.payment_method', 'acme/future', 'PaymentPanel', fn () => ['method' => 'bank_transfer']);
        $slots->register('storefront.footer', 'acme/future', 'FooterPanel', fn () => []);
        $slots->register('storefront.overlay', 'acme/future', 'OverlayPanel', fn () => []);
        $variant = $this->sellable('12.50', 5);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('slots.0.component', 'acme/future/HomePanel')->has('storefront.footerSlots', 1)->has('storefront.overlays', 1));
        $this->get('/p/'.$variant->product->slug)->assertInertia(fn (Assert $page) => $page->where('slots.0.props.id', $variant->product_id));
        $this->addToCart($variant, 2);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('slots.0.component', 'acme/future/CartPanel'));
        $checkoutResponse = $this->get('/checkout');
        $checkoutResponse->assertInertia(function (Assert $page) {
            $page->where('slots.0.component', 'acme/future/CheckoutPanel')->where('paymentSlots.0.props.method', 'bank_transfer');
            $props = $page->toArray()['props'];
            $this->assertSame($props['summary']['grand_total'], $props['slots'][0]['props']['total']);
        });
        $this->get('/checkout?country=US', ['X-Inertia' => 'true', 'X-Inertia-Version' => $checkoutResponse->viewData('page')['version'] ?? '', 'X-Inertia-Partial-Component' => 'storefront/checkout', 'X-Inertia-Partial-Data' => 'summary,paymentSlots,slots'])
            ->assertOk()->assertJsonPath('props.slots.0.component', 'acme/future/CheckoutPanel');
        $slots->register('catalog.after_products', 'acme/broken', 'BrokenPanel', fn () => throw new \RuntimeException('Optional extension failed'));
        $this->get('/search')->assertOk();
    }
}
