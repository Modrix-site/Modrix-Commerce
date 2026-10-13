<?php

namespace Tests\Feature\Catalog;

use App\Core\Media\Media;
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

    public function test_slugs_are_never_empty_or_too_long()
    {
        $manager = $this->userWithRole('catalog-manager');

        $this->actingAs($manager)->post(route('admin.brands.store'), ['name' => 'Symbols', 'slug' => '!!!'])->assertSessionHasNoErrors();
        $this->assertSame('symbols', Brand::query()->where('name', 'Symbols')->value('slug'));

        $this->post(route('admin.brands.store'), ['name' => str_repeat('щ', 120)])->assertSessionHasNoErrors();
        $slug = (string) Brand::query()->where('name', str_repeat('щ', 120))->value('slug');
        $this->assertNotSame('', $slug);
        $this->assertLessThanOrEqual(140, strlen($slug));

        $brand = Brand::query()->where('name', 'Symbols')->firstOrFail();
        $this->put(route('admin.brands.update', $brand), ['name' => 'Symbols', 'slug' => '???'])->assertSessionHasNoErrors();
        $this->assertSame('symbols', $brand->fresh()?->slug);
    }

    public function test_the_current_logo_is_always_offered()
    {
        $old = Media::query()->forceCreate(['disk' => 'public', 'path' => 'media/old.png', 'collection' => 'library', 'filename' => 'old.png', 'mime' => 'image/png', 'size' => 10]);
        foreach (range(1, 101) as $i) {
            Media::query()->forceCreate(['disk' => 'public', 'path' => "media/n{$i}.png", 'collection' => 'library', 'filename' => "n{$i}.png", 'mime' => 'image/png', 'size' => 10]);
        }
        Brand::query()->create(['name' => 'Logo', 'slug' => 'logo', 'logo_media_id' => $old->id]);

        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.brands.index'))
            ->assertInertia(fn (Assert $page) => $page->where('logos', fn ($logos) => collect($logos)->contains('id', $old->id)));

        // Only images can be logos.
        $pdf = Media::query()->forceCreate(['disk' => 'public', 'path' => 'media/a.pdf', 'collection' => 'library', 'filename' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 10]);
        $this->post(route('admin.brands.store'), ['name' => 'Pdf', 'logo_media_id' => $pdf->id])->assertSessionHasErrors('logo_media_id');
    }

    public function test_brands_need_products_update()
    {
        $this->actingAs($this->userWithRole('support-agent'))->get(route('admin.brands.index'))->assertForbidden();
        $this->post(route('admin.brands.store'), ['name' => 'X'])->assertForbidden();
    }
}
