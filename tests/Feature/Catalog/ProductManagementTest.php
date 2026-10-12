<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Actions\SaveAttribute;
use App\Domains\Catalog\Events\ProductCreated;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    /**
     * @return array<string, mixed>
     */
    private function simplePayload(array $overrides = [], array $variant = []): array
    {
        return array_merge([
            'type' => 'simple',
            'name' => 'Organic Cotton Tee',
            'status' => 'published',
            'description' => 'Soft and durable.',
            'category_ids' => [],
            'variants' => [array_merge([
                'sku' => 'TEE-001',
                'price' => '24.50',
                'compare_at_price' => '30.00',
                'track_inventory' => true,
                'allow_backorder' => false,
                'is_active' => true,
                'initial_stock' => 12,
            ], $variant)],
        ], $overrides);
    }

    public function test_catalog_manager_creates_a_simple_product_with_price_and_initial_stock()
    {
        Event::fake([ProductCreated::class]);
        $category = Category::factory()->create();

        $this->actingAs($this->userWithRole('catalog-manager'))
            ->post(route('admin.products.store'), $this->simplePayload(['category_ids' => [$category->id]]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $product = Product::query()->where('name', 'Organic Cotton Tee')->firstOrFail();
        $this->assertSame('organic-cotton-tee', $product->slug);
        $this->assertNotNull($product->published_at);
        $this->assertTrue($product->categories->contains($category));

        $variant = $product->variants()->with('prices', 'stocks')->firstOrFail();
        $this->assertSame('TEE-001', $variant->sku);
        $this->assertSame('24.50', $variant->priceIn('USD')?->toDisplayDecimal());
        $this->assertSame('30.00', $variant->compareAtPriceIn('USD')?->toDisplayDecimal());
        $this->assertSame(12, $variant->onHand());
        $this->assertDatabaseHas('inventory_movements', ['variant_id' => $variant->id, 'quantity_change' => 12, 'reason' => 'initial']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.created', 'subject_id' => $product->id]);
        Event::assertDispatched(ProductCreated::class);
    }

    public function test_price_is_stored_as_decimal_in_the_store_currency()
    {
        $this->actingAs($this->owner())->post(route('admin.products.store'), $this->simplePayload([], ['price' => '0.1', 'compare_at_price' => null]));

        $this->assertDatabaseHas('product_prices', ['currency' => 'USD', 'amount' => '0.1000']);
    }

    public function test_validation_rejects_bad_input()
    {
        ProductVariant::factory()->create(['sku' => 'TAKEN-1']);
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('admin.products.store'), $this->simplePayload([], ['sku' => 'taken-1']))
            ->assertSessionHasErrors('variants.0.sku');

        $this->actingAs($owner)
            ->post(route('admin.products.store'), $this->simplePayload([], ['price' => '12.345678']))
            ->assertSessionHasErrors('variants.0.price');

        $this->actingAs($owner)
            ->post(route('admin.products.store'), $this->simplePayload([], ['price' => '-5']))
            ->assertSessionHasErrors('variants.0.price');

        $this->actingAs($owner)
            ->post(route('admin.products.store'), $this->simplePayload([], ['compare_at_price' => '10.00']))
            ->assertSessionHasErrors('variants.0.compare_at_price');

        $payload = $this->simplePayload();
        $payload['variants'][] = array_merge($payload['variants'][0], ['sku' => 'TEE-002']);
        $this->actingAs($owner)
            ->post(route('admin.products.store'), $payload)
            ->assertSessionHasErrors('variants');

        // The only product is the one owning the pre-existing TAKEN-1 variant.
        $this->assertDatabaseMissing('products', ['name' => 'Organic Cotton Tee']);
    }

    public function test_variable_product_requires_one_value_per_option_and_unique_combinations()
    {
        $size = app(SaveAttribute::class)->handle(null, ['name' => 'Size', 'values' => [['value' => 'S'], ['value' => 'M']]]);
        $color = app(SaveAttribute::class)->handle(null, ['name' => 'Color', 'values' => [['value' => 'Red']]]);
        [$s, $m] = $size->values->pluck('id')->all();
        $red = $color->values->first()->id;
        $owner = $this->owner();

        $base = [
            'type' => 'variable',
            'name' => 'Hoodie',
            'status' => 'draft',
            'option_attribute_ids' => [$size->id, $color->id],
        ];

        $this->actingAs($owner)->post(route('admin.products.store'), $base + ['variants' => [
            ['sku' => 'H-S', 'price' => '40', 'option_value_ids' => [$s]],
        ]])->assertSessionHasErrors('variants.0.option_value_ids');

        $this->actingAs($owner)->post(route('admin.products.store'), $base + ['variants' => [
            ['sku' => 'H-S', 'price' => '40', 'option_value_ids' => [$s, $red]],
            ['sku' => 'H-S2', 'price' => '40', 'option_value_ids' => [$red, $s]],
        ]])->assertSessionHasErrors('variants.1.option_value_ids');

        $this->actingAs($owner)->post(route('admin.products.store'), $base + ['variants' => [
            ['sku' => 'H-S', 'price' => '40', 'option_value_ids' => [$s, $red]],
            ['sku' => 'H-M', 'price' => '42', 'option_value_ids' => [$m, $red]],
        ]])->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Hoodie')->firstOrFail();
        $this->assertSame(['S / Red', 'M / Red'], $product->variants->pluck('name')->all());
    }

    public function test_updating_removes_omitted_variants_softly_and_keeps_stock_history()
    {
        $size = app(SaveAttribute::class)->handle(null, ['name' => 'Size', 'values' => [['value' => 'S'], ['value' => 'M']]]);
        [$s, $m] = $size->values->pluck('id')->all();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.products.store'), [
            'type' => 'variable', 'name' => 'Cap', 'status' => 'published',
            'option_attribute_ids' => [$size->id],
            'variants' => [
                ['sku' => 'CAP-S', 'price' => '10', 'option_value_ids' => [$s], 'initial_stock' => 3],
                ['sku' => 'CAP-M', 'price' => '10', 'option_value_ids' => [$m], 'initial_stock' => 3],
            ],
        ])->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Cap')->firstOrFail();
        $keep = $product->variants->firstWhere('sku', 'CAP-S');

        $this->actingAs($owner)->put(route('admin.products.update', $product), [
            'type' => 'variable', 'name' => 'Cap', 'status' => 'published',
            'option_attribute_ids' => [$size->id],
            'variants' => [
                ['id' => $keep->id, 'sku' => 'CAP-S', 'price' => '11', 'option_value_ids' => [$s]],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['CAP-S'], $product->fresh()->variants->pluck('sku')->all());
        $this->assertSoftDeleted('product_variants', ['sku' => 'CAP-M']);
        $this->assertDatabaseHas('inventory_movements', ['quantity_change' => 3, 'reason' => 'initial', 'variant_id' => ProductVariant::withTrashed()->where('sku', 'CAP-M')->value('id')]);
    }

    public function test_cannot_attach_another_products_variant()
    {
        $other = Product::factory()->withVariant()->create();
        $mine = Product::factory()->withVariant()->create();
        $foreignId = $other->variants()->value('id');

        $this->actingAs($this->owner())
            ->put(route('admin.products.update', $mine), $this->simplePayload([], ['id' => $foreignId, 'sku' => 'NEW-SKU']))
            ->assertSessionHasErrors('variants.0.id');
    }

    public function test_permissions_are_enforced()
    {
        $product = Product::factory()->withVariant()->create();

        $this->actingAs($this->userWithRole('analyst'))->get(route('admin.products.index'))->assertOk();
        $this->actingAs($this->userWithRole('analyst'))->get(route('admin.products.edit', $product))->assertForbidden();
        $this->actingAs($this->userWithRole('analyst'))->post(route('admin.products.store'), $this->simplePayload())->assertForbidden();
        $this->actingAs($this->userWithRole('order-manager'))->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('support-agent'))->delete(route('admin.products.destroy', $product))->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_deleting_a_product_soft_deletes_it()
    {
        $product = Product::factory()->withVariant()->create();

        $this->actingAs($this->userWithRole('catalog-manager'))
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.deleted']);
    }

    public function test_index_searches_by_sku_and_filters_by_status()
    {
        Product::factory()->withVariant(variantAttributes: ['sku' => 'FIND-ME'])->create(['name' => 'Alpha']);
        Product::factory()->draft()->withVariant()->create(['name' => 'Beta']);

        $this->actingAs($this->owner())
            ->get(route('admin.products.index', ['search' => 'find-me']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Alpha'));

        $this->actingAs($this->owner())
            ->get(route('admin.products.index', ['status' => 'draft']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Beta'));
    }
}
