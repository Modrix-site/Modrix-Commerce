<?php

namespace Tests\Feature\Extensions;

use App\Core\Catalog\ProductLabels;
use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Domains\Catalog\Models\Product;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\MovementReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * The bundled "Product Label" module: badges on product cards from the
 * merchant's rules, and the admin page.
 */
class ProductLabelTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private ModuleManager $modules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules'), $this->modulesPath);
        $this->modules = app(ModuleManager::class);
        $this->modules->install('ecomodular/product-label');
        $this->modules->enable('ecomodular/product-label', true);
    }

    private function state(): ModuleState
    {
        return Extension::query()->where('name', 'ecomodular/product-label')->firstOrFail()->state;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function labels(Product $product): array
    {
        // Reload variants (with prices and stock) so the resolver sees fresh data.
        $product->load(['variants' => fn ($q) => $q->where('is_active', true), 'variants.prices', 'variants.stocks']);

        return app(ProductLabels::class)->labels($product);
    }

    public function test_module_is_enabled_and_registers_its_admin_route()
    {
        $this->assertSame(ModuleState::Enabled, $this->state());
        $this->assertTrue(app('router')->has('admin.ecomodular.product-label.index'));
        $this->assertTrue(app(ProductLabels::class)->isActive());
    }

    public function test_a_newly_published_product_gets_a_new_label()
    {
        $product = $this->sellable('20.00')->product;

        $this->modules->configure('ecomodular/product-label', [
            'enabled' => '1',
            'show_new' => '1',
            'new_days' => '21',
            'show_sale' => '0',
            'show_low_stock' => '0',
            'show_sold_out' => '0',
            'show_featured' => '0',
        ], $this->owner());

        $product->forceFill(['published_at' => now()->subDay()])->save();
        $product->refresh();

        $labels = $this->labels($product);

        $this->assertSame(['new'], array_column($labels, 'key'));
        $this->assertSame('New', $labels[0]['text']);
        $this->assertSame('new', $labels[0]['tone']);
    }

    public function test_an_old_product_gets_no_new_label()
    {
        $product = $this->sellable('20.00')->product;
        $product->forceFill(['published_at' => now()->subDays(90)])->save();
        $product->refresh();

        $this->assertNotContains('new', array_column($this->labels($product), 'key'));
    }

    public function test_a_discounted_product_gets_a_sale_label()
    {
        $variant = $this->sellable('20.00');
        // Give the price a higher compare-at price so it reads as on sale.
        $variant->prices()->update(['compare_at_amount' => '30.0000']);

        $this->modules->configure('ecomodular/product-label', [
            'enabled' => '1',
            'show_new' => '0',
            'show_sale' => '1',
            'show_low_stock' => '0',
            'show_sold_out' => '0',
        ], $this->owner());

        $labels = $this->labels($variant->product);

        $this->assertContains('sale', array_column($labels, 'key'));
    }

    public function test_a_low_stock_product_gets_a_low_stock_label()
    {
        $variant = $this->sellable('20.00', 3);
        $product = $variant->product;
        $product->forceFill(['published_at' => now()->subDays(90)])->save();
        $product->refresh();
        $variant->refresh();

        $this->modules->configure('ecomodular/product-label', [
            'enabled' => '1',
            'show_new' => '0',
            'show_sale' => '0',
            'show_low_stock' => '1',
            'show_sold_out' => '0',
            'low_stock_threshold' => '5',
        ], $this->owner());

        $this->assertContains('low-stock', array_column($this->labels($variant->product), 'key'));
    }

    public function test_a_sold_out_product_gets_a_sold_out_label()
    {
        $variant = $this->sellable('20.00', 1);
        app(InventoryService::class)->adjust($variant, -1, MovementReason::Adjustment);
        $variant->refresh();

        $this->modules->configure('ecomodular/product-label', [
            'enabled' => '1',
            'show_new' => '0',
            'show_sale' => '0',
            'show_low_stock' => '0',
            'show_sold_out' => '1',
        ], $this->owner());

        $this->assertContains('sold-out', array_column($this->labels($variant->product), 'key'));
    }

    public function test_custom_sku_labels_attach_to_the_matching_product()
    {
        $variant = $this->sellable('20.00');
        $product = $variant->product;
        $product->forceFill(['published_at' => now()->subDays(90)])->save();
        $product->refresh();

        $this->modules->configure('ecomodular/product-label', [
            'enabled' => '1',
            'show_new' => '0',
            'show_sale' => '0',
            'show_low_stock' => '0',
            'show_sold_out' => '0',
            'custom_labels' => $variant->sku.' = Best seller',
        ], $this->owner());

        $labels = $this->labels($variant->product);

        $this->assertContains('Best seller', array_column($labels, 'text'));
    }

    public function test_labels_are_capped_at_the_configured_maximum()
    {
        $variant = $this->sellable('20.00', 3);
        $variant->prices()->update(['compare_at_amount' => '30.0000']);
        $product = $variant->product;
        $product->forceFill(['published_at' => now()->subDay(), 'is_featured' => true])->save();

        $this->modules->configure('ecomodular/product-label', [
            'enabled' => '1',
            'show_new' => '1',
            'show_sale' => '1',
            'show_low_stock' => '1',
            'show_featured' => '1',
            'max_labels' => '2',
        ], $this->owner());

        $this->assertCount(2, $this->labels($product->refresh()));
    }

    public function test_disabled_module_adds_no_labels()
    {
        $product = $this->sellable('20.00')->product;
        $product->forceFill(['published_at' => now()->subDay()])->save();

        $this->modules->configure('ecomodular/product-label', ['enabled' => '0'], $this->owner());

        $this->assertSame([], $this->labels($product->refresh()));
    }

    public function test_cards_carry_labels_on_the_storefront()
    {
        $variant = $this->sellable('20.00');
        $product = $variant->product;
        $product->forceFill(['published_at' => now()->subDay()])->save();
        $this->taxRate('US', '0');

        $this->modules->configure('ecomodular/product-label', [
            'enabled' => '1',
            'show_new' => '1',
            'show_sale' => '0',
            'show_low_stock' => '0',
            'show_sold_out' => '0',
        ], $this->owner());

        // The storefront product page carries the product's labels.
        $this->get(route('storefront.product', $product->refresh()->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('product.labels', 1)
                ->where('product.labels.0.key', 'new'));
    }

    public function test_admin_page_requires_the_labels_permission()
    {
        $this->actingAs($this->userWithRole('catalog-manager'))
            ->get(route('admin.ecomodular.product-label.index'))
            ->assertForbidden();

        $this->actingAs($this->owner())
            ->get(route('admin.ecomodular.product-label.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/ecomodular/product-label/admin/index', false)
                ->where('state.enabled', true)
                ->has('state.rules'));
    }
}
