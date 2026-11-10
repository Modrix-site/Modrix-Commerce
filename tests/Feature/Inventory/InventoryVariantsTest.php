<?php

namespace Tests\Feature\Inventory;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\MovementReason;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class InventoryVariantsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
        $this->installPlatform();
    }

    /**
     * A product with tracked variants at the given stock levels, plus optional untracked ones.
     *
     * @param  list<int>  $stocks
     */
    private function product(string $name, array $stocks, int $untracked = 0): Product
    {
        $product = Product::factory()->create(['name' => $name]);

        foreach ($stocks as $i => $stock) {
            $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => "Size {$i}", 'position' => $i, 'sku' => strtoupper(substr($name, 0, 3))."-{$i}"]);
            if ($stock > 0) {
                app(InventoryService::class)->adjust($variant, $stock, MovementReason::Initial);
            }
        }

        for ($i = 0; $i < $untracked; $i++) {
            ProductVariant::factory()->create(['product_id' => $product->id, 'track_inventory' => false, 'position' => 10 + $i]);
        }

        return $product;
    }

    public function test_inventory_lists_one_row_per_product_with_its_variants_and_totals(): void
    {
        $this->product('Hoodie', [5, 0, 30], untracked: 1);
        $this->product('Mug', [12]);

        $this->actingAs($this->owner())->get(route('admin.inventory.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 2)
                // Most urgent first: the hoodie has a variant out of stock.
                ->where('products.data.0.name', 'Hoodie')
                ->where('products.data.0.available', 35)
                ->where('products.data.0.out', 1)
                ->where('products.data.0.untracked', 1)
                ->has('products.data.0.variants', 3)
                ->where('products.data.0.variants.0.name', 'Size 0')
                ->where('products.data.0.variants.1.state', 'out')
                ->where('products.data.0.variants.0.url', fn ($url) => str_contains($url, '/variants/'))
                ->where('products.data.1.name', 'Mug')
                ->has('products.data.1.variants', 1));
    }

    public function test_search_by_variant_sku_and_stock_filters_mark_the_matching_variants(): void
    {
        $this->product('Hoodie', [5, 0, 30]);
        $this->product('Mug', [12]);

        $this->actingAs($this->owner())->get(route('admin.inventory.index', ['search' => 'HOO-2']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->has('products.data.0.variants', 3)
                ->where('products.data.0.variants.2.match', true)
                ->where('products.data.0.variants.0.match', false));

        $this->get(route('admin.inventory.index', ['stock' => 'out']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Hoodie')
                ->where('products.data.0.variants.1.match', true));
    }

    public function test_committed_counts_units_sold_but_not_shipped(): void
    {
        $this->enableCheckout();
        $variant = $this->sellable('20.00', 10);
        $this->addToCart($variant, 3);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertRedirect();
        $order = Order::query()->latest('id')->firstOrFail();

        $page = fn () => $this->actingAs($this->owner())->get(route('admin.products.variants.show', [$variant->product_id, $variant->id]));

        // Placing the order took 3 from available; they are committed until shipped.
        $page()->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('admin/products/variant')
            ->where('locations.0.available', 7)
            ->where('locations.0.committed', 3)
            ->where('locations.0.on_hand', 10));

        $order->forceFill(['fulfillment_status' => FulfillmentStatus::Shipped])->save();
        $page()->assertInertia(fn (Assert $p) => $p->where('locations.0.committed', 0)->where('locations.0.on_hand', 7));

        $order->forceFill(['fulfillment_status' => FulfillmentStatus::Unfulfilled, 'status' => OrderStatus::Cancelled])->save();
        $page()->assertInertia(fn (Assert $p) => $p->where('locations.0.committed', 0));
    }

    public function test_the_variant_page_saves_price_inventory_and_shipping_fields(): void
    {
        $product = $this->product('Hoodie', [5, 2]);
        [$first, $second] = $product->variants()->orderBy('position')->get()->all();
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('admin.products.variants.show', [$product, $second]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('product.variants', 2)
                ->where('variant.id', $second->id)
                ->where('variant.sku', 'HOO-1'));

        $url = route('admin.products.variants.update', [$product, $second]);
        $valid = ['sku' => 'hoo-blue', 'barcode' => '0123', 'price' => '43.55', 'compare_at_price' => '50', 'cost' => '20', 'track_inventory' => true, 'allow_backorder' => true, 'low_stock_threshold' => 3, 'weight' => '0.5', 'length' => '10', 'width' => '5', 'height' => '2'];

        // SKUs stay unique; compare-at must be above the price.
        $this->put($url, [...$valid, 'sku' => $first->sku])->assertSessionHasErrors('sku');
        $this->put($url, [...$valid, 'compare_at_price' => '40'])->assertSessionHasErrors('compare_at_price');

        $this->put($url, $valid)->assertRedirect(route('admin.products.variants.show', [$product, $second]));
        $second->refresh();
        $this->assertSame('HOO-BLUE', $second->sku);
        $this->assertTrue($second->allow_backorder);
        $this->assertSame(3, $second->low_stock_threshold);
        $this->assertSame('43.5500', (string) $second->prices()->first()->amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.variant_updated']);

        // A variant of another product is not reachable through this one.
        $other = $this->product('Mug', [1])->variants()->firstOrFail();
        $this->get(route('admin.products.variants.show', [$product, $other]))->assertNotFound();
        $this->actingAs($this->userWithRole('support-agent'))->get(route('admin.products.variants.show', [$product, $second]))->assertForbidden();
    }
}
