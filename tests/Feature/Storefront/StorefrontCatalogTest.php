<?php

namespace Tests\Feature\Storefront;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\StorefrontCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    public function test_only_visible_priced_products_are_listed()
    {
        Product::factory()->withVariant('10.00')->create(['name' => 'Visible']);
        Product::factory()->draft()->withVariant()->create(['name' => 'Draft']);
        Product::factory()->withVariant()->create(['name' => 'Scheduled', 'published_at' => now()->addWeek()]);
        Product::factory()->withVariant(currency: 'EUR')->create(['name' => 'Wrong currency']);
        Product::factory()->withVariant()->create(['name' => 'Deleted'])->delete();

        $this->get(route('storefront.search'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/listing')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Visible')
                ->where('products.data.0.price.amount', '10.00')
                ->where('storefront.cartCount', 0));
    }

    public function test_product_page_hides_unpublished_products()
    {
        $visible = Product::factory()->withVariant()->create();
        $draft = Product::factory()->draft()->withVariant()->create();

        $this->get(route('storefront.product', $visible->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('storefront/product')->where('product.id', $visible->id));
        $this->get(route('storefront.product', $draft->slug))->assertNotFound();
    }

    public function test_category_listing_includes_subcategories_and_hides_inactive_categories()
    {
        $parent = Category::factory()->create(['slug' => 'apparel']);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $hidden = Category::factory()->create(['slug' => 'secret', 'is_active' => false]);

        Product::factory()->withVariant()->create(['name' => 'In child'])->categories()->attach($child);
        Product::factory()->withVariant()->create(['name' => 'Elsewhere']);

        $this->get(route('storefront.category', 'apparel'))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'In child'));
        $this->get(route('storefront.category', 'secret'))->assertNotFound();
        $this->assertFalse($hidden->is_active);
    }

    public function test_search_matches_name_and_sku_and_escapes_wildcards()
    {
        Product::factory()->withVariant(variantAttributes: ['sku' => 'BLUE-MUG'])->create(['name' => 'Coffee cup']);
        Product::factory()->withVariant()->create(['name' => '100% Wool Scarf']);
        Product::factory()->withVariant()->create(['name' => 'Socks']);

        $this->get(route('storefront.search', ['q' => 'blue-mug']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Coffee cup'));

        $this->get(route('storefront.search', ['q' => '%']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', '100% Wool Scarf'));
    }

    public function test_price_sorting()
    {
        Product::factory()->withVariant('30.00')->create(['name' => 'Mid']);
        Product::factory()->withVariant('5.00')->create(['name' => 'Cheap']);
        Product::factory()->withVariant('99.00')->create(['name' => 'Pricey']);

        $this->get(route('storefront.search', ['sort' => 'price_asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.data.0.name', 'Cheap')
                ->where('products.data.2.name', 'Pricey'));

        $this->get(route('storefront.search', ['sort' => 'bogus']))->assertSessionHasErrors('sort');
    }

    public function test_out_of_stock_products_are_flagged()
    {
        Product::factory()->withVariant(stock: 0, variantAttributes: ['track_inventory' => true])->create(['name' => 'Gone']);

        $this->get(route('storefront.search'))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.in_stock', false));
    }

    public function test_cards_carry_the_buy_button_override()
    {
        // Null means "follow the theme"; the field is passed through as-is.
        $inherit = Product::factory()->withVariant()->create(['name' => 'Inherits', 'show_buy_button' => null]);
        $on = Product::factory()->withVariant()->create(['name' => 'Forced on', 'show_buy_button' => true]);
        $off = Product::factory()->withVariant()->create(['name' => 'Forced off', 'show_buy_button' => false]);

        $cards = collect(app(StorefrontCatalog::class)
            ->listing(app(StoreSettings::class)->currency(), null)
            ->items())
            ->keyBy('name');

        $this->assertNull($cards['Inherits']['show_buy_button']);
        $this->assertTrue($cards['Forced on']['show_buy_button']);
        $this->assertFalse($cards['Forced off']['show_buy_button']);
        // A simple, in-stock product exposes the variant the Buy button adds.
        $this->assertIsInt($cards['Inherits']['variant_id']);
    }
}
