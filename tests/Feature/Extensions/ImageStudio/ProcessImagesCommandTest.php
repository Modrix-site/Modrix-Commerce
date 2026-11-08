<?php

namespace Tests\Feature\Extensions\ImageStudio;

use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ImageStudio\Jobs\ProcessProductImage;
use EcoModular\ImageStudio\Models\ImageVariant;
use EcoModular\ImageStudio\Services\ImageStudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CommerceFixtures;
use Tests\Support\ImageStudioFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class ProcessImagesCommandTest extends TestCase
{
    use CommerceFixtures, ImageStudioFixtures, ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootImageStudio();
    }

    private function variantStatus(ProductImage $image): ?string
    {
        return ImageVariant::query()->where('product_image_id', $image->id)->value('status');
    }

    public function test_sync_processes_all_unprocessed(): void
    {
        $a = $this->studioImage();
        $b = $this->studioImage();

        $this->artisan('image-studio:process', ['--all' => true, '--sync' => true])
            ->expectsOutputToContain('Processed: 2 done, 0 skipped, 0 failed.')
            ->assertExitCode(0);

        $this->assertSame(ImageVariant::DONE, $this->variantStatus($a));
        $this->assertSame(ImageVariant::DONE, $this->variantStatus($b));
    }

    public function test_product_filter_and_force(): void
    {
        $done = $this->studioImage();
        $other = $this->studioImage();
        ImageVariant::query()->create([
            'product_image_id' => $done->id, 'disk' => 'public', 'status' => ImageVariant::DONE,
            'path' => 'products/x/studio/keep.webp', 'driver' => 'sentinel',
        ]);

        $this->artisan('image-studio:process', ['--all' => true, '--sync' => true])->assertExitCode(0);
        $this->assertSame('sentinel', ImageVariant::query()->where('product_image_id', $done->id)->value('driver'));

        // Product filter: only the requested product's images are touched.
        ImageVariant::query()->where('product_image_id', $other->id)->delete();
        $this->artisan('image-studio:process', ['--product' => [(string) $done->product_id], '--sync' => true])->assertExitCode(0);
        $this->assertNotSame($done->product_id, $other->product_id);
        $this->assertNull($this->variantStatus($other));

        $this->artisan('image-studio:process', ['--product' => [(string) $done->product_id], '--force' => true, '--sync' => true])
            ->assertExitCode(0);
        $this->assertNotSame('sentinel', ImageVariant::query()->where('product_image_id', $done->id)->value('driver'));
        $this->assertSame(ImageVariant::DONE, $this->variantStatus($done));
    }

    public function test_queue_mode_dispatches_without_processing(): void
    {
        Queue::fake();
        $a = $this->studioImage();

        $this->artisan('image-studio:process', ['--all' => true])
            ->expectsOutputToContain('Queued 1 images.')
            ->assertExitCode(0);

        Queue::assertPushed(ProcessProductImage::class, 1);
        $this->assertSame(ImageVariant::PENDING, $this->variantStatus($a));
    }

    public function test_mark_pending_does_not_dispatch(): void
    {
        Queue::fake();
        $image = $this->studioImage();

        app(ImageStudio::class)->markPending($image);

        Queue::assertNothingPushed();
        $this->assertSame(ImageVariant::PENDING, $this->variantStatus($image));
    }

    public function test_requires_scope_option(): void
    {
        $this->artisan('image-studio:process')->assertExitCode(1);
    }
}
