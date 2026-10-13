<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrandAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    public function test_brands_are_created_updated_and_listed_with_product_counts()
    {
        $manager = $this->userWithRole('catalog-manager');

        $this->actingAs($manager)->post(route('admin.brands.store'), ['name' => 'Acme Outdoor', 'description' => 'Gear for trails.'])->assertSessionHasNoErrors();
        $brand = Brand::query()->firstOrFail();
        $this->assertSame('acme-outdoor', $brand->slug);
        Product::factory()->create(['brand_id' => $brand->id]);

        $this->get(route('admin.brands.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/brands/index')
            ->where('brands.0.name', 'Acme Outdoor')
            ->where('brands.0.products_count', 1));

        $this->put(route('admin.brands.update', $brand), ['name' => 'Acme', 'slug' => 'acme', 'description' => null])->assertSessionHasNoErrors();
        $this->assertSame(['Acme', 'acme'], [$brand->fresh()?->name, $brand->fresh()?->slug]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'brand.updated']);

        // Slugs are unique.
        $this->post(route('admin.brands.store'), ['name' => 'Other', 'slug' => 'acme'])->assertSessionHasErrors('slug');
        $this->post(route('admin.brands.store'), ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_deleting_a_brand_keeps_its_products()
    {
        $brand = Brand::query()->create(['name' => 'Gone', 'slug' => 'gone']);
        $product = Product::factory()->create(['brand_id' => $brand->id]);

        $this->actingAs($this->userWithRole('catalog-manager'))->delete(route('admin.brands.destroy', $brand))->assertRedirect();

        $this->assertNull(Brand::query()->find($brand->id));
        $this->assertNull($product->fresh()?->brand_id);
        $this->get(route('admin.products.edit', $product))->assertOk();
    }

    public function test_brands_need_products_update()
    {
        $this->actingAs($this->userWithRole('support-agent'))->get(route('admin.brands.index'))->assertForbidden();
        $this->post(route('admin.brands.store'), ['name' => 'X'])->assertForbidden();
    }
}
