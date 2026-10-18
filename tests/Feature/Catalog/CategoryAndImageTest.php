<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryAndImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    public function test_nested_categories_and_cycle_prevention()
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.categories.store'), ['name' => 'Apparel', 'is_active' => true])->assertSessionHasNoErrors();
        $apparel = Category::query()->where('slug', 'apparel')->firstOrFail();

        $this->actingAs($owner)->post(route('admin.categories.store'), ['name' => 'Shirts', 'parent_id' => $apparel->id])->assertSessionHasNoErrors();
        $shirts = Category::query()->where('slug', 'shirts')->firstOrFail();
        $this->assertSame($apparel->id, $shirts->parent_id);

        $this->actingAs($owner)
            ->put(route('admin.categories.update', $apparel), ['name' => 'Apparel', 'parent_id' => $shirts->id])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($owner)
            ->delete(route('admin.categories.destroy', $apparel))
            ->assertSessionHasErrors('category');

        $this->assertSame([$apparel->id, $shirts->id], $apparel->descendantIdsAndSelf());
    }

    public function test_duplicate_explicit_slug_is_rejected()
    {
        Category::factory()->create(['slug' => 'sale']);

        $this->actingAs($this->owner())
            ->post(route('admin.categories.store'), ['name' => 'Sale', 'slug' => 'sale'])
            ->assertSessionHasErrors('slug');
    }

    public function test_categories_and_subcategories_have_independent_images(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('admin.categories.store'), [
            'name' => 'Apparel', 'is_active' => true,
            'image' => UploadedFile::fake()->image('apparel.jpg', 60, 40),
        ])->assertSessionHasNoErrors();
        $parent = Category::query()->where('slug', 'apparel')->firstOrFail();

        $this->post(route('admin.categories.store'), [
            'name' => 'Shirts', 'parent_id' => $parent->id, 'is_active' => true,
            'image' => UploadedFile::fake()->image('shirts.png', 50, 50),
        ])->assertSessionHasNoErrors();
        $child = Category::query()->where('slug', 'shirts')->firstOrFail();
        $this->assertNotSame($parent->image_path, $child->image_path);
        Storage::disk('public')->assertExists([$parent->image_path, $child->image_path]);
        $this->assertMatchesRegularExpression('#^categories/[A-Za-z0-9]{40}\\.jpg$#', $parent->image_path);

        $this->get(route('storefront.category', $parent->slug))->assertInertia(fn ($page) => $page
            ->component('storefront/listing')
            ->where('image', $parent->imageUrl())
            ->where('subcategories.0.image_url', $child->imageUrl()));

        $original = $child->image_path;
        $this->put(route('admin.categories.update', $child), ['name' => 'Shirts', 'parent_id' => $parent->id])->assertSessionHasNoErrors();
        $this->assertSame($original, $child->fresh()->image_path);
        $this->post(route('admin.categories.update', $child), [
            '_method' => 'put', 'name' => 'Shirts', 'parent_id' => $parent->id,
            'image' => UploadedFile::fake()->image('new.webp'),
        ])->assertSessionHasNoErrors();
        $this->assertNotSame($original, $child->fresh()->image_path);
        $this->put(route('admin.categories.update', $child), [
            'name' => 'Shirts', 'parent_id' => $parent->id, 'remove_image' => true,
        ])->assertSessionHasNoErrors();
        $this->assertNull($child->fresh()->imageUrl());
        $this->assertSame($parent->image_path, $parent->fresh()->image_path);
    }

    public function test_invalid_category_images_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs($this->owner())->post(route('admin.categories.store'), [
            'name' => 'Invalid image',
            'image' => UploadedFile::fake()->createWithContent('bad.jpg', '<?php echo "bad"; ?>'),
        ])->assertSessionHasErrors('image');
        $this->assertDatabaseMissing('categories', ['name' => 'Invalid image']);
        $this->assertSame([], Storage::disk('public')->allFiles('categories'));
    }

    public function test_uploaded_images_are_reencoded_and_stored_with_random_names()
    {
        Storage::fake('public');
        $product = Product::factory()->withVariant()->create();

        // A valid JPEG with a PHP payload appended: re-encoding must drop it.
        $image = UploadedFile::fake()->image('photo.jpg', 40, 30);
        file_put_contents($image->getRealPath(), file_get_contents($image->getRealPath()).'<?php echo "pwned"; ?>');

        $this->actingAs($this->owner())
            ->post(route('admin.products.images.store', $product), ['images' => [$image]])
            ->assertSessionHasNoErrors();

        $stored = $product->images()->firstOrFail();
        $this->assertMatchesRegularExpression('#^products/'.$product->id.'/[A-Za-z0-9]{40}\.jpg$#', $stored->path);
        $this->assertSame(40, $stored->width);
        Storage::disk('public')->assertExists($stored->path);
        $this->assertStringNotContainsString('<?php', Storage::disk('public')->get($stored->path));
    }

    public function test_non_images_are_rejected()
    {
        Storage::fake('public');
        $product = Product::factory()->withVariant()->create();
        $fake = UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]); ?>');

        $this->actingAs($this->owner())
            ->post(route('admin.products.images.store', $product), ['images' => [$fake]])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_images_cannot_be_modified_through_another_product()
    {
        Storage::fake('public');
        $a = Product::factory()->withVariant()->create();
        $b = Product::factory()->withVariant()->create();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.products.images.store', $a), ['images' => [UploadedFile::fake()->image('a.png')]]);
        $image = $a->images()->firstOrFail();

        $this->actingAs($owner)
            ->delete(route('admin.products.images.destroy', ['product' => $b->id, 'image' => $image->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }
}
