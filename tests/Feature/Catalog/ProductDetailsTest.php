<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductDetailsTest extends TestCase
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
    private function payload(array $overrides = [], array $variant = []): array
    {
        return array_merge([
            'type' => 'simple',
            'name' => 'Trail Mug',
            'status' => 'published',
            'category_ids' => [],
            'variants' => [array_merge([
                'sku' => 'MUG-TR-1',
                'price' => '20.00',
                'track_inventory' => true,
                'allow_backorder' => false,
                'is_active' => true,
                'initial_stock' => 5,
            ], $variant)],
        ], $overrides);
    }

    private function brand(string $name = 'Northwind'): Brand
    {
        return Brand::query()->create(['name' => $name, 'slug' => strtolower($name)]);
    }

    public function test_products_save_brand_tags_featured_cost_and_dimensions()
    {
        $brand = $this->brand();

        $this->actingAs($this->userWithRole('catalog-manager'))->post(route('admin.products.store'), $this->payload([
            'brand_id' => $brand->id,
            'is_featured' => true,
            'tags' => ['  Camping ', 'camping', 'Gift  Ideas', 'enamel'],
        ], ['cost' => '8.50', 'length' => '12.5', 'width' => '9', 'height' => '10']))->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Trail Mug')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertTrue($product->is_featured);
        $this->assertSame(['camping', 'enamel', 'gift ideas'], $product->tagNames());
        $this->assertSame('8.5000', $variant->cost);
        $this->assertSame(['12.50', '9.00', '10.00'], [$variant->length, $variant->width, $variant->height]);

        $this->get(route('admin.products.edit', $product))->assertInertia(fn (Assert $page) => $page
            ->where('product.brand_id', $brand->id)
            ->where('product.is_featured', true)
            ->where('product.tags', ['camping', 'enamel', 'gift ideas'])
            ->where('product.variants.0.cost', '8.50')
            ->where('product.variants.0.length', '12.50')
            ->where('brands.0.name', 'Northwind')
            ->where('tagSuggestions', fn ($tags) => collect($tags)->contains('camping'))
            ->where('dimensionUnit', 'cm'));

        // Clearing works too.
        $this->put(route('admin.products.update', $product), $this->payload(['brand_id' => null, 'is_featured' => false, 'tags' => []], [
            'id' => $variant->id, 'cost' => null, 'length' => null,
        ]))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertNull($product->brand_id);
        $this->assertFalse($product->is_featured);
        $this->assertSame([], $product->tagNames());
        $this->assertNull($variant->fresh()?->cost);
    }

    public function test_invalid_details_are_refused()
    {
        $this->actingAs($this->userWithRole('catalog-manager'));

        $this->post(route('admin.products.store'), $this->payload(['brand_id' => 999]))->assertSessionHasErrors('brand_id');
        $this->post(route('admin.products.store'), $this->payload(['tags' => array_map(fn ($i) => "tag{$i}", range(1, 21))]))->assertSessionHasErrors('tags');
        $this->post(route('admin.products.store'), $this->payload(['tags' => [str_repeat('x', 61)]]))->assertSessionHasErrors('tags.0');
        $this->post(route('admin.products.store'), $this->payload([], ['cost' => '-1']))->assertSessionHasErrors('variants.0.cost');
        $this->post(route('admin.products.store'), $this->payload([], ['height' => '200000']))->assertSessionHasErrors('variants.0.height');
        $this->assertSame(0, Product::query()->count());
    }

    public function test_product_list_filters_by_brand_tag_and_featured()
    {
        $brand = $this->brand();
        $manager = $this->userWithRole('catalog-manager');
        $this->actingAs($manager)->post(route('admin.products.store'), $this->payload(['name' => 'Branded', 'brand_id' => $brand->id, 'tags' => ['50%_off']], ['sku' => 'B-1']))->assertSessionHasNoErrors();
        $this->post(route('admin.products.store'), $this->payload(['name' => 'Featured', 'is_featured' => true, 'tags' => ['50x off']], ['sku' => 'F-1']))->assertSessionHasNoErrors();

        $names = fn (array $query) => collect($this->get(route('admin.products.index', $query))->inertiaProps('products.data'))->pluck('name')->all();

        $this->assertSame(['Branded'], $names(['brand' => $brand->id]));
        $this->assertSame(['Featured'], $names(['featured' => 1]));
        // Tags match literally (no wildcards).
        $this->assertSame(['Branded'], $names(['tag' => '50%_off']));
        $this->get(route('admin.products.index', ['brand' => $brand->id]))->assertInertia(fn (Assert $page) => $page->where('products.data.0.brand', 'Northwind'));
    }
}
