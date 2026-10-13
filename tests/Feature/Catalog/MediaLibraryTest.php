<?php

namespace Tests\Feature\Catalog;

use App\Core\Media\Media;
use App\Core\Settings\SettingsRepository;
use App\Core\Themes\ThemeCustomizations;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        Storage::fake('public');
    }

    private function upload(int $count = 1): void
    {
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $files[] = UploadedFile::fake()->image("photo-{$i}.jpg", 40, 30);
        }

        $this->post(route('admin.media.store'), ['files' => $files, 'alt' => 'Linen shirt'])->assertSessionHasNoErrors();
    }

    public function test_staff_upload_browse_and_describe_images()
    {
        $this->actingAs($this->userWithRole('catalog-manager'));
        $this->upload(2);

        $media = Media::query()->orderBy('id')->get();
        $this->assertCount(2, $media);
        $this->assertSame('library', $media[0]->collection);
        $this->assertSame('Linen shirt', $media[0]->alt);
        Storage::disk('public')->assertExists($media[0]->path);

        $this->get(route('admin.media.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/media/index')->has('media.data', 2));
        $this->get(route('admin.media.index', ['search' => 'photo-1']))
            ->assertInertia(fn (Assert $page) => $page->has('media.data', 1)->where('media.data.0.filename', 'photo-1.jpg'));

        $this->put(route('admin.media.update', $media[0]), ['alt' => 'Blue linen shirt'])->assertRedirect();
        $this->assertSame('Blue linen shirt', $media[0]->fresh()->alt);

        $this->getJson(route('admin.media.picker', ['search' => 'photo']))->assertJsonCount(2, 'media');
    }

    public function test_only_staff_with_the_permission_reach_the_library()
    {
        $this->actingAs($this->userWithRole('order-manager'))->get(route('admin.media.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('analyst'))->post(route('admin.media.store'), ['files' => [UploadedFile::fake()->image('a.jpg')]])->assertForbidden();
    }

    public function test_non_images_and_payloads_are_refused_or_stripped()
    {
        $this->actingAs($this->owner());
        $this->post(route('admin.media.store'), ['files' => [UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]); ?>')]])
            ->assertSessionHasErrors();
        $this->assertSame(0, Media::query()->count());
    }

    public function test_library_images_are_reused_on_products_and_protected_while_used()
    {
        $this->actingAs($owner = $this->owner());
        $this->upload();
        $media = Media::query()->firstOrFail();
        $product = Product::factory()->create(['name' => 'Shirt']);

        $this->post(route('admin.products.images.from-library', $product), ['media' => [$media->id]])->assertRedirect();
        $this->post(route('admin.products.images.from-library', $product), ['media' => [$media->id]])->assertRedirect();
        $this->assertSame(1, $product->images()->count());
        $image = $product->images()->firstOrFail();
        $this->assertSame($media->path, $image->path);

        // In use: cannot be deleted, and the usage is reported.
        $this->getJson(route('admin.media.show', $media))->assertJsonPath('usage.products.0.name', 'Shirt');
        $this->delete(route('admin.media.destroy', $media))->assertSessionHasErrors('media');
        Storage::disk('public')->assertExists($media->path);

        // Removing it from the product keeps the library file.
        $this->delete(route('admin.products.images.destroy', ['product' => $product, 'image' => $image]))->assertRedirect();
        Storage::disk('public')->assertExists($media->path);

        // Now unused: it can be deleted.
        $this->delete(route('admin.media.destroy', $media))->assertSessionHasNoErrors();
        $this->assertNull($media->fresh());
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_product_uploads_appear_in_the_library_and_are_cleaned_up_with_the_product_image()
    {
        $this->actingAs($this->owner());
        $product = Product::factory()->create();
        $this->post(route('admin.products.images.store', $product), ['images' => [UploadedFile::fake()->image('front.jpg', 40, 30)]])->assertSessionHasNoErrors();

        $image = ProductImage::query()->firstOrFail();
        $media = Media::query()->firstOrFail();
        $this->assertSame([Media::PRODUCTS, $image->path], [$media->collection, $media->path]);

        // Shared with a second product: deleting from the first keeps the file.
        $other = Product::factory()->create();
        $this->post(route('admin.products.images.from-library', $other), ['media' => [$media->id]]);
        $this->delete(route('admin.products.images.destroy', ['product' => $product, 'image' => $image]));
        Storage::disk('public')->assertExists($media->path);

        // The last use goes: the product upload is removed with it.
        $this->delete(route('admin.products.images.destroy', ['product' => $other, 'image' => $other->images()->firstOrFail()]));
        Storage::disk('public')->assertMissing($media->path);
        $this->assertNull($media->fresh());
    }

    public function test_library_images_can_be_used_in_themes_and_are_then_protected()
    {
        $this->actingAs($this->owner());
        $this->upload();
        $media = Media::query()->firstOrFail();

        $values = array_column(app(ThemeCustomizations::class)->uploads(), 'value');
        $this->assertContains($media->themeValue(), $values);

        app(SettingsRepository::class)->set('theme.ecomodular.essence.design', ['logo' => $media->themeValue()]);
        $this->delete(route('admin.media.destroy', $media))->assertSessionHasErrors('media');
        $this->getJson(route('admin.media.show', $media))->assertJsonPath('usage.themes', true);
    }
}
