<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Actions\SaveAttribute;
use App\Domains\Catalog\Enums\ProductType;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTypeChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    public function test_simple_product_can_become_variable_without_losing_existing_stock_or_image(): void
    {
        $product = Product::factory()->withVariant('20', 12)->create();
        $variant = $product->variants->firstOrFail();
        $image = $product->images()->create(['disk' => 'public', 'path' => 'products/photo.png', 'position' => 0]);
        $variant->update(['image_id' => $image->id]);
        $option = app(SaveAttribute::class)->handle(null, ['name' => 'Size', 'values' => [['value' => 'S'], ['value' => 'M']]]);
        $values = $option->values->pluck('id')->all();
        $this->actingAs($this->userWithRole('catalog-manager'))->put(route('admin.products.update', $product), [
            'name' => $product->name, 'type' => 'variable', 'status' => 'published', 'option_attribute_ids' => [$option->id],
            'variants' => [
                ['id' => $variant->id, 'sku' => $variant->sku, 'price' => '20', 'image_id' => $image->id, 'option_value_ids' => [$values[0]]],
                ['sku' => 'CONVERT-M', 'price' => '25', 'initial_stock' => 4, 'option_value_ids' => [$values[1]]],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame(ProductType::Variable, $product->refresh()->type);
        $this->assertSame(12, $variant->stocks()->sum('on_hand'));
        $this->assertSame($image->id, $variant->refresh()->image_id);
        $this->assertSame('S', $variant->name);
        $this->assertSame(4, $product->variants()->where('sku', 'CONVERT-M')->firstOrFail()->stocks()->sum('on_hand'));
    }

    public function test_single_variant_product_can_become_simple_and_keep_its_stock(): void
    {
        $product = Product::factory()->withVariant('20', 7)->create(['type' => 'variable']);
        $variant = $product->variants->firstOrFail();
        $option = app(SaveAttribute::class)->handle(null, ['name' => 'Color', 'values' => [['value' => 'Red']]]);
        $variant->options()->sync($option->values->pluck('id')->all());
        $this->actingAs($this->owner())->put(route('admin.products.update', $product), [
            'name' => $product->name, 'type' => 'simple', 'status' => 'published',
            'variants' => [['id' => $variant->id, 'sku' => $variant->sku, 'price' => '20']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(ProductType::Simple, $product->refresh()->type);
        $this->assertSame(0, $variant->options()->count());
        $this->assertSame(7, $variant->stocks()->sum('on_hand'));
    }

    public function test_incomplete_conversion_keeps_the_simple_product_unchanged(): void
    {
        $product = Product::factory()->withVariant('20', 9)->create();
        $variant = $product->variants->firstOrFail();
        $this->actingAs($this->owner())->put(route('admin.products.update', $product), [
            'name' => 'Must not change', 'type' => 'variable', 'status' => 'published',
            'variants' => [['id' => $variant->id, 'sku' => $variant->sku, 'price' => '20']],
        ])->assertSessionHasErrors('option_attribute_ids');
        $this->assertSame(ProductType::Simple, $product->refresh()->type);
        $this->assertNotSame('Must not change', $product->name);
        $this->assertSame(9, $variant->stocks()->sum('on_hand'));
    }
}
