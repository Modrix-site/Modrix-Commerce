<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\ThemeDemoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class TempoThemeTest extends TestCase
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
        $file = new UploadedFile(base_path('dist/ecomodular-tempo-1.0.0.zip'), 'tempo.zip', 'application/zip', null, true);
        $this->actingAs($this->userWithRole('administrator'))
            ->post(route('admin.themes.packages.store'), ['package' => $file])->assertSessionHasNoErrors();
        $theme = Extension::where('name', 'ecomodular/tempo')->firstOrFail();
        $this->assertSame(ModuleState::Discovered, $theme->state);
        $this->assertNull($theme->applied_manifest);
        $this->assertCount(67, $theme->manifest['customizer']['fields']);
        $this->assertCount(12, $theme->manifest['sections']['home']);
        $this->assertSame('src/product.tsx', $theme->manifest['frontend']['product']);
        $this->assertDatabaseMissing('products', ['slug' => 'tempo-demo-1']);
    }

    public function test_campaign_assets_and_tile_customizations_resolve_and_demo_is_additive(): void
    {
        app(ThemeManager::class)->activate('ecomodular/tempo');
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('storefront.navigation.header.links', 9)
            ->where('storefront.theme.settings.brand', 'Tempo')
            ->where('sections.0.settings.heading', 'New season. New discoveries.')
            ->where('sections.0.data.image', fn ($url) => str_contains($url, '/theme-assets/ecomodular/tempo/assets/season.png'))
            ->where('sections.3.settings.style', 'card')
            ->where('sections.3.settings.columns', '7')
            ->has('sections.3.data.tiles', 7));
        $this->get('/theme-assets/ecomodular/tempo/assets/season.png')->assertOk();
        $extension = Extension::where('name', 'ecomodular/tempo')->firstOrFail();
        $manifest = ThemeManifest::fromArray($extension->manifest);
        $importer = app(ThemeDemoImporter::class);
        $this->assertSame(['categories' => 1, 'products' => 5], $importer->import($manifest, $this->owner()));
        $this->assertSame(['categories' => 0, 'products' => 0], $importer->import($manifest, $this->owner()));
    }

    public function test_filters_and_extension_slots_use_platform_context_and_live_totals(): void
    {
        app(ThemeManager::class)->activate('ecomodular/tempo');
        $category = Category::factory()->create(['slug' => 'tempo-test-category']);
        $variant = $this->sellable('12.50', 5, product: ['name' => 'Tempo sweater']);
        $variant->product->categories()->attach($category);
        $this->sellable('50', 0);
        $slots = app(StorefrontSlots::class);
        $slots->register('home.after_featured', 'acme/future', 'HomePanel', fn () => []);
        $slots->register('catalog.after_products', 'acme/future', 'CatalogPanel', fn ($context) => ['category' => $context['category']?->slug, 'query' => $context['query']]);
        $slots->register('product.after_details', 'acme/future', 'ProductPanel', fn ($context) => ['id' => $context['product']->id]);
        $slots->register('cart.after_totals', 'acme/future', 'CartPanel', fn ($context) => ['total' => $context['cart']->toArray()['grand_total']]);
        $slots->register('checkout.after_summary', 'acme/future', 'CheckoutPanel', fn ($context) => ['total' => $context['cart']->toArray()['grand_total']]);
        $slots->register('checkout.payment_method', 'acme/future', 'PaymentPanel', fn () => ['method' => 'bank_transfer']);
        $slots->register('storefront.footer', 'acme/future', 'FooterPanel', fn () => []);
        $slots->register('storefront.overlay', 'acme/future', 'OverlayPanel', fn () => []);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('slots', 1)->has('storefront.footerSlots', 1)->has('storefront.overlays', 1));
        $this->get('/c/tempo-test-category')->assertInertia(fn (Assert $page) => $page->where('slots.0.props.category', $category->slug));
        $this->get('/search?q=Tempo&in_stock=1&min_price=10&max_price=20&sort=price_desc')->assertInertia(fn (Assert $page) => $page->where('products.total', 1)->where('products.data.0.id', $variant->product_id)->where('slots.0.props.query', 'Tempo'));
        $this->get('/p/'.$variant->product->slug)->assertInertia(fn (Assert $page) => $page->where('slots.0.props.id', $variant->product_id));
        $this->addToCart($variant, 2);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('slots.0.component', 'acme/future/CartPanel'));
        $this->get('/checkout')->assertInertia(function (Assert $page) {
            $page->where('slots.0.component', 'acme/future/CheckoutPanel')->has('paymentSlots', 1);
            $props = $page->toArray()['props'];
            $this->assertSame($props['summary']['grand_total'], $props['slots'][0]['props']['total']);
        });
    }
}
