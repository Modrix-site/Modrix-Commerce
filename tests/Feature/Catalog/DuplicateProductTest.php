<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductFile;
use App\Domains\Catalog\ProductRelations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class DuplicateProductTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    private Product $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        Storage::fake('local');

        $variant = $this->sellable('25.00', 7, ['sku' => 'MUG-1', 'barcode' => '4006381333931', 'cost' => '9.5000'], [
            'name' => 'Trail Mug', 'slug' => 'trail-mug', 'status' => ProductStatus::Published,
            'brand_id' => Brand::query()->create(['name' => 'Northwind', 'slug' => 'northwind'])->id,
            'is_featured' => true, 'is_digital' => true,
        ]);
        $variant->prices()->create(['currency' => 'EUR', 'amount' => '23.0000']);
        $this->source = $variant->product;
        $this->source->categories()->attach(Category::query()->create(['name' => 'Mugs', 'slug' => 'mugs']));
        $this->source->tags()->create(['tag' => 'camping']);
        $this->source->translations()->create(['locale' => 'es', 'name' => 'Taza']);
        $this->source->images()->create(['disk' => 'public', 'path' => 'products/mug.jpg', 'alt' => 'Mug', 'position' => 0]);
        app(ProductRelations::class)->sync($this->source, [$this->sellable()->product_id], [$this->sellable()->product_id]);
        Storage::disk('local')->put("downloads/{$this->source->id}/guide.pdf", 'pdf');
        $this->source->files()->create(['name' => 'Guide.pdf', 'disk' => 'local', 'path' => "downloads/{$this->source->id}/guide.pdf", 'size' => 3, 'position' => 0]);

        $this->actingAs($this->userWithRole('catalog-manager'));
    }

    private function duplicate(): Product
    {
        $this->post(route('admin.products.duplicate', $this->source))->assertSessionHasNoErrors()->assertRedirect();

        return Product::query()->latest('id')->firstOrFail();
    }

    public function test_copy_is_a_full_hidden_draft()
    {
        $copy = $this->duplicate();

        $this->assertSame('Copy of Trail Mug', $copy->name);
        $this->assertSame('trail-mug-copy', $copy->slug);
        $this->assertSame(ProductStatus::Draft, $copy->status);
        $this->assertSame([$this->source->brand_id, true, true], [$copy->brand_id, $copy->is_featured, $copy->is_digital]);
        $this->assertSame($this->source->categories()->pluck('categories.id')->all(), $copy->categories()->pluck('categories.id')->all());
        $this->assertSame(['camping'], $copy->tagNames());
        $this->assertSame('Copy of Taza', $copy->translations()->where('locale', 'es')->value('name'));
        $this->assertSame(['products/mug.jpg'], $copy->images()->pluck('path')->all());
        $this->assertSame($this->source->relatedProducts()->pluck('products.id')->all(), $copy->relatedProducts()->pluck('products.id')->all());
        $this->assertSame($this->source->upsellProducts()->pluck('products.id')->all(), $copy->upsellProducts()->pluck('products.id')->all());

        $variant = $copy->variants()->with('prices')->firstOrFail();
        $this->assertSame('MUG-1-COPY', $variant->sku);
        $this->assertNull($variant->barcode);
        $this->assertSame('9.5000', $variant->cost);
        $this->assertEqualsCanonicalizing(['USD' => '25.0000', 'EUR' => '23.0000'], $variant->prices->pluck('amount', 'currency')->all());
        $this->assertSame(0, (int) $variant->onHand());
        $this->assertSame(7, (int) $this->source->variants()->firstOrFail()->onHand());
    }

    public function test_duplicating_twice_gets_unique_slug_and_skus()
    {
        $this->duplicate();
        $second = $this->duplicate();

        $this->assertSame('trail-mug-copy-2', $second->slug);
        $this->assertSame('MUG-1-COPY-2', $second->variants()->firstOrFail()->sku);
    }

    public function test_files_are_copied_on_private_disk()
    {
        $copy = $this->duplicate();

        $file = $copy->files()->firstOrFail();
        $this->assertSame('Guide.pdf', $file->name);
        $this->assertStringStartsWith("downloads/{$copy->id}/", $file->path);
        Storage::disk('local')->assertExists($file->path);

        // Deleting the copy's file keeps the original's.
        $file->delete();
        Storage::disk('local')->assertExists(ProductFile::query()->where('product_id', $this->source->id)->value('path'));
    }

    public function test_duplicate_needs_products_create()
    {
        $this->actingAs($this->userWithRole('order-manager'));
        $this->post(route('admin.products.duplicate', $this->source))->assertForbidden();
    }
}
