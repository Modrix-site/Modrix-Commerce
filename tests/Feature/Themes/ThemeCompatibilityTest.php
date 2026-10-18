<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeRepository;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\ThemeDemoImporter;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

/**
 * Every bundled theme must leave core commerce untouched: browsing, cart,
 * checkout and the account area work and totals are identical.
 */
class ThemeCompatibilityTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpThemes(withBundled: true);
        $this->enableCheckout();
        Mail::fake();
        Storage::fake(config('ecomodular.media_disk'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function bundledThemes(): array
    {
        return [
            'Essence' => ['ecomodular/essence'],
            'Noir' => ['ecomodular/noir'],
            'Bloom' => ['ecomodular/bloom'],
            'Harvest' => ['ecomodular/harvest'],
            'Aurora' => ['ecomodular/aurora'],
            'Prism' => ['ecomodular/prism'],
            'Nord' => ['ecomodular/nord'],
        ];
    }

    #[DataProvider('bundledThemes')]
    public function test_bundled_theme_is_valid(string $name)
    {
        app(ThemeRepository::class)->sync();
        $theme = Extension::query()->where('type', 'theme')->where('name', $name)->firstOrFail();

        $this->assertNotSame(ModuleState::Invalid, $theme->state, implode(' ', (array) $theme->errors));
    }

    #[DataProvider('bundledThemes')]
    public function test_storefront_and_checkout_work_with_the_theme(string $name)
    {
        app(ThemeManager::class)->activate($name);

        $category = Category::factory()->create(['name' => 'Mugs', 'slug' => 'mugs']);
        $variant = $this->sellable('12.50', 5, product: ['name' => 'Speckled Mug']);
        $variant->product->categories()->attach($category);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.name', $name)
            ->has('sections'));
        $this->get(route('storefront.category', 'mugs'))->assertOk();
        $this->get(route('storefront.search', ['q' => 'mug']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
        $this->get(route('storefront.product', $variant->product->slug))->assertOk();

        $this->addToCart($variant, 2);
        $this->get(route('cart.show'))->assertOk();
        $this->get(route('checkout.show'))->assertOk();
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertRedirect();

        // Totals are computed by the core, identical under every theme: 2 × 12.50 + 5.00 shipping.
        $order = Order::query()->firstOrFail();
        $this->assertSame('30.0000', $order->grand_total);
        $this->get(route('checkout.complete', $order))->assertOk();

        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('account.show'))->assertOk();
        $this->actingAs($customer)->get(route('account.orders'))->assertOk();
    }

    #[DataProvider('bundledThemes')]
    public function test_home_sections_resolve_their_images(string $name)
    {
        app(ThemeManager::class)->activate($name);

        $this->get('/')->assertInertia(function (Assert $page) {
            foreach ($page->toArray()['props']['sections'] as $section) {
                if (array_key_exists('image', $section['data']) && $section['data']['image'] !== null) {
                    $this->get(parse_url($section['data']['image'], PHP_URL_PATH).'?'.parse_url($section['data']['image'], PHP_URL_QUERY))->assertOk();
                }
            }
        });
    }

    public function test_demo_content_import_is_additive_and_idempotent()
    {
        $owner = $this->owner();
        // Existing content with a colliding slug and SKU must be left alone.
        $existing = $this->sellable('99.00', 1, ['sku' => 'NOIR-RING-01'], ['name' => 'My own ring', 'slug' => 'my-ring']);
        $manifest = app(ThemeManager::class)->renderedManifest(tap(app(ThemeRepository::class))->sync()->find('ecomodular/noir'));

        $first = app(ThemeDemoImporter::class)->import($manifest, $owner);
        $second = app(ThemeDemoImporter::class)->import($manifest, $owner);

        $this->assertSame(['categories' => 2, 'products' => 4], $first);
        $this->assertSame(['categories' => 0, 'products' => 0], $second);
        $this->assertSame('My own ring', $existing->product->fresh()->name);
        $this->assertSame('99.00', ProductVariant::query()->where('sku', 'NOIR-RING-01')->firstOrFail()->priceIn('USD')?->toDisplayDecimal());
        $this->assertSame(1, Product::query()->where('slug', 'noir-prism-pendant')->firstOrFail()->images()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.demo_imported']);
    }

    public function test_demo_import_through_the_admin_requires_confirmation()
    {
        app(ThemeRepository::class)->sync();
        $bloom = Extension::query()->where('type', 'theme')->where('name', 'ecomodular/bloom')->firstOrFail();

        $this->actingAs($this->owner())->post(route('admin.themes.demo', $bloom))->assertSessionHasErrors('confirmed');
        $this->assertSame(0, Product::query()->count());

        $this->actingAs($this->owner())->post(route('admin.themes.demo', $bloom), ['confirmed' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(6, Product::query()->count());
    }
}
