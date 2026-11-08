<?php

namespace Tests\Feature\Extensions\ImageStudio;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ImageStudio\Jobs\ProcessProductImage;
use EcoModular\ImageStudio\Models\ImageVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ImageStudioFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class ImageStudioAdminTest extends TestCase
{
    use CommerceFixtures, ImageStudioFixtures, ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootImageStudio();
        // Module pages live in the module, not resources/js/pages (same as DeveloperToolsTest).
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function variant(ProductImage $image, string $status, array $extra = []): ImageVariant
    {
        return ImageVariant::query()->create(array_merge([
            'product_image_id' => $image->id, 'disk' => 'public', 'status' => $status,
        ], $extra));
    }

    public function test_index_renders_for_owner(): void
    {
        $image = $this->studioImage();
        $this->variant($image, ImageVariant::DONE, ['path' => 'products/1/studio/a.webp', 'bytes_before' => 1000, 'bytes_after' => 400]);
        $this->studioImage();
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('admin.modrixcommerce.image-studio.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/image-studio/admin/index')
                ->where('hasRemoveBgKey', false)
                ->where('driver', 'gd-edge')
                ->where('stats.done', 1)
                ->has('images.data', 2)
                ->has('images.data.0', fn (Assert $row) => $row
                    ->hasAll(['id', 'productName', 'originalUrl', 'variantUrl', 'status', 'error', 'bytesBefore', 'bytesAfter'])));

        $this->actingAs($owner)
            ->get(route('admin.modrixcommerce.image-studio.index', ['status' => 'unprocessed']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('images.data', 1)
                ->where('images.data.0.status', 'unprocessed'));

        $this->actingAs($owner)
            ->put(route('admin.modrixcommerce.image-studio.removebg-key'), ['key' => 'rb-secret-key-123'])
            ->assertRedirect();

        $response = $this->actingAs($owner)->get(route('admin.modrixcommerce.image-studio.index'));
        $response->assertInertia(fn (Assert $page) => $page->where('hasRemoveBgKey', true));
        $this->assertStringNotContainsString('rb-secret-key-123', (string) $response->getContent());
    }

    public function test_process_all_queues_only_unprocessed(): void
    {
        Queue::fake();
        $done = $this->studioImage();
        $this->variant($done, ImageVariant::DONE, ['path' => 'products/1/studio/a.webp']);
        $a = $this->studioImage();
        $b = $this->studioImage();

        $this->actingAs($this->owner())->post(route('admin.modrixcommerce.image-studio.process-all'))
            ->assertRedirect()->assertSessionHas('success');

        Queue::assertPushed(ProcessProductImage::class, 2);
        $this->assertSame(ImageVariant::PENDING, ImageVariant::query()->where('product_image_id', $a->id)->value('status'));
        $this->assertSame(ImageVariant::PENDING, ImageVariant::query()->where('product_image_id', $b->id)->value('status'));
        $this->assertSame(ImageVariant::DONE, ImageVariant::query()->where('product_image_id', $done->id)->value('status'));
    }

    public function test_retry_requeues_failed_and_skipped(): void
    {
        Queue::fake();
        $failed = $this->studioImage();
        $skipped = $this->studioImage();
        $done = $this->studioImage();
        $this->variant($failed, ImageVariant::FAILED, ['error' => 'boom']);
        $this->variant($skipped, ImageVariant::SKIPPED);
        $this->variant($done, ImageVariant::DONE, ['path' => 'products/1/studio/a.webp']);

        $this->actingAs($this->owner())->post(route('admin.modrixcommerce.image-studio.retry'))
            ->assertRedirect()->assertSessionHas('success');

        Queue::assertPushed(ProcessProductImage::class, 2);
        $this->assertSame(ImageVariant::PENDING, ImageVariant::query()->where('product_image_id', $failed->id)->value('status'));
        $this->assertSame(ImageVariant::PENDING, ImageVariant::query()->where('product_image_id', $skipped->id)->value('status'));
        $this->assertSame(ImageVariant::DONE, ImageVariant::query()->where('product_image_id', $done->id)->value('status'));
    }

    public function test_process_and_revert_single_image(): void
    {
        Queue::fake();
        $image = $this->studioImage();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.modrixcommerce.image-studio.process', ['image' => $image->id]))
            ->assertRedirect();
        Queue::assertPushed(ProcessProductImage::class, 1);

        Storage::disk('public')->put('products/1/studio/x.webp', 'x');
        ImageVariant::query()->where('product_image_id', $image->id)
            ->update(['status' => 'done', 'path' => 'products/1/studio/x.webp']);

        $this->actingAs($owner)->delete(route('admin.modrixcommerce.image-studio.revert', ['image' => $image->id]))
            ->assertRedirect();
        $this->assertDatabaseCount('image_studio_variants', 0);
        Storage::disk('public')->assertMissing('products/1/studio/x.webp');

        // No variant left: a no-op redirect.
        $this->actingAs($owner)->delete(route('admin.modrixcommerce.image-studio.revert', ['image' => $image->id]))
            ->assertRedirect();
    }

    public function test_revert_all_deletes_rows_and_files(): void
    {
        Storage::disk('public')->put('products/1/studio/a.webp', 'a');
        Storage::disk('public')->put('products/1/studio/b.webp', 'b');
        $this->variant($this->studioImage(), ImageVariant::DONE, ['path' => 'products/1/studio/a.webp']);
        $this->variant($this->studioImage(), ImageVariant::DONE, ['path' => 'products/1/studio/b.webp']);

        $this->actingAs($this->owner())->post(route('admin.modrixcommerce.image-studio.revert-all'))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseCount('image_studio_variants', 0);
        Storage::disk('public')->assertMissing('products/1/studio/a.webp');
        Storage::disk('public')->assertMissing('products/1/studio/b.webp');
    }

    public function test_removebg_key_is_stored_encrypted_and_cleared(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->put(route('admin.modrixcommerce.image-studio.removebg-key'), ['key' => 'rb-secret-key-123'])
            ->assertRedirect();

        $this->assertSame('rb-secret-key-123', app(SecretSettings::class)->get('image_studio.removebg_api_key'));
        $raw = (string) app(SettingsRepository::class)->get('secrets.image_studio.removebg_api_key');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString('rb-secret-key-123', $raw);

        $this->actingAs($owner)->put(route('admin.modrixcommerce.image-studio.removebg-key'), ['key' => ''])
            ->assertRedirect();
        $this->assertFalse(app(SecretSettings::class)->has('image_studio.removebg_api_key'));

        $this->actingAs($owner)->put(route('admin.modrixcommerce.image-studio.removebg-key'), ['key' => str_repeat('a', 201)])
            ->assertSessionHasErrors('key');
    }

    public function test_routes_forbidden_without_permission(): void
    {
        $role = $this->roleWithPermissions('clerk', []);
        $user = $this->userWithRole($role->name);

        $this->actingAs($user)->get(route('admin.modrixcommerce.image-studio.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.modrixcommerce.image-studio.process-all'))->assertForbidden();
    }
}
