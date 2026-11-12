<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class ProductFilesTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        Storage::fake('local');
        $this->product = $this->sellable('15.00', null)->product;
        $this->product->forceFill(['is_digital' => true])->save();
        $this->actingAs($this->userWithRole('catalog-manager'));
    }

    private function upload(string $name = 'guide.pdf'): void
    {
        $this->post(route('admin.products.files.store', $this->product), [
            'file' => UploadedFile::fake()->create($name, 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();
    }

    public function test_files_upload_to_private_disk_only()
    {
        $this->upload();

        $file = ProductFile::query()->firstOrFail();
        $this->assertSame('guide.pdf', $file->name);
        $this->assertSame('local', $file->disk);
        $this->assertStringStartsWith("downloads/{$this->product->id}/", $file->path);
        Storage::disk('local')->assertExists($file->path);
        $this->assertFileDoesNotExist(public_path($file->path));
        $this->assertSame(120 * 1024, $file->size);

        $this->get(route('admin.products.edit', $this->product))->assertInertia(fn (Assert $page) => $page
            ->where('product.is_digital', true)
            ->where('product.files.0.name', 'guide.pdf'));
    }

    public function test_eleventh_file_is_rejected()
    {
        foreach (range(1, ProductFile::MAX_PER_PRODUCT) as $i) {
            $this->upload("file-{$i}.pdf");
        }

        $this->post(route('admin.products.files.store', $this->product), [
            'file' => UploadedFile::fake()->create('one-more.pdf', 10),
        ])->assertSessionHasErrors('file');
        $this->assertSame(ProductFile::MAX_PER_PRODUCT, ProductFile::query()->count());
    }

    public function test_rename_reorder_delete_removes_stored_file()
    {
        $this->upload('a.pdf');
        $this->upload('b.pdf');
        [$a, $b] = ProductFile::query()->orderBy('id')->get()->all();

        $this->put(route('admin.products.files.update', [$this->product, $b]), ['name' => 'Manual (EN).pdf', 'position' => 0])->assertSessionHasNoErrors();
        $this->assertSame(['Manual (EN).pdf', 'a.pdf'], $this->product->files()->pluck('name')->all());

        // A slash or backslash would break the download's file name.
        $this->put(route('admin.products.files.update', [$this->product, $b]), ['name' => 'Part 1/2.pdf'])->assertSessionHasErrors('name');
        $this->put(route('admin.products.files.update', [$this->product, $b]), ['name' => 'AC\\DC.zip'])->assertSessionHasErrors('name');

        $this->delete(route('admin.products.files.destroy', [$this->product, $a]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($a->path);
        $this->assertSame(1, ProductFile::query()->count());

        // A file of another product is not reachable through this product.
        $other = $this->sellable()->product;
        $this->delete(route('admin.products.files.destroy', [$other, $b]))->assertNotFound();
    }

    public function test_files_need_products_update()
    {
        $this->actingAs($this->userWithRole('order-manager'));

        $this->post(route('admin.products.files.store', $this->product), [
            'file' => UploadedFile::fake()->create('guide.pdf', 10),
        ])->assertForbidden();
    }

    public function test_digital_flag_saves()
    {
        $this->post(route('admin.products.store'), [
            'type' => 'simple',
            'name' => 'Ebook',
            'status' => 'published',
            'is_digital' => true,
            'category_ids' => [],
            'variants' => [['sku' => 'EBOOK-1', 'price' => '9.00', 'is_active' => true, 'allow_backorder' => false]],
        ])->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Ebook')->firstOrFail();
        $this->assertTrue($product->is_digital);
        // Digital products do not track stock unless asked to.
        $this->assertFalse($product->variants()->firstOrFail()->track_inventory);
    }
}
