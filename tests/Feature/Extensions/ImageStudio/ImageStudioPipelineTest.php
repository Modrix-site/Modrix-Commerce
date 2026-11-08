<?php

namespace Tests\Feature\Extensions\ImageStudio;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Media\ProductImageUrls;
use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Actions\StoreProductImage;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\StorefrontCatalog;
use EcoModular\ImageStudio\ImageStudioServiceProvider;
use EcoModular\ImageStudio\Jobs\ProcessProductImage;
use EcoModular\ImageStudio\Models\ImageVariant;
use EcoModular\ImageStudio\Services\ImageProcessor;
use EcoModular\ImageStudio\Services\ImageStudio;
use EcoModular\ImageStudio\Services\ProcessResult;
use EcoModular\ImageStudio\Services\VariantUrls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\CommerceFixtures;
use Tests\Support\ImageStudioFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class ImageStudioPipelineTest extends TestCase
{
    use CommerceFixtures, ImageStudioFixtures, ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootImageStudio();
    }

    private function setting(string $key, mixed $value): void
    {
        app(SettingsRepository::class)->setMany(["extension.modrixcommerce.image-studio.{$key}" => $value]);
    }

    private function noisy(): ProductImage
    {
        return $this->studioImage('png', 64, 64, function (\GdImage $c, int $w, int $h): void {
            mt_srand(7);
            for ($x = 0; $x < $w; $x++) {
                for ($y = 0; $y < $h; $y++) {
                    imagesetpixel($c, $x, $y, imagecolorallocate($c, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
                }
            }
        });
    }

    private function process(ProductImage $image): void
    {
        app(ImageStudio::class)->markPending($image);
        ProcessProductImage::dispatchSync($image->id);
    }

    private function variantRow(ProductImage $image): ?ImageVariant
    {
        return ImageVariant::query()->where('product_image_id', $image->id)->first();
    }

    public function test_upload_queues_job_and_creates_pending_row(): void
    {
        Queue::fake();
        $product = $this->sellable()->product;

        $image = app(StoreProductImage::class)->handle($product, UploadedFile::fake()->image('a.jpg', 300, 300));

        Queue::assertPushed(ProcessProductImage::class);
        $this->assertSame(ImageVariant::PENDING, $this->variantRow($image)?->status);
    }

    public function test_upload_not_queued_when_process_on_upload_off(): void
    {
        Queue::fake();
        $this->setting('process_on_upload', false);
        $product = $this->sellable()->product;

        $image = app(StoreProductImage::class)->handle($product, UploadedFile::fake()->image('a.jpg', 300, 300));

        Queue::assertNothingPushed();
        $this->assertNull($this->variantRow($image));
    }

    public function test_upload_not_queued_when_disabled(): void
    {
        Queue::fake();
        $this->setting('enabled', false);
        $product = $this->sellable()->product;

        app(StoreProductImage::class)->handle($product, UploadedFile::fake()->image('a.jpg', 300, 300));

        Queue::assertNothingPushed();
    }

    public function test_job_marks_done_and_storefront_uses_variant(): void
    {
        $image = $this->studioImage();

        $this->process($image);

        $row = $this->variantRow($image);
        $this->assertSame(ImageVariant::DONE, $row->status);
        $this->assertGreaterThan(0, $row->bytes_after);
        $this->assertNull($row->error);
        Storage::disk('public')->assertExists($row->path);

        $url = $image->fresh()->url();
        $this->assertStringEndsWith('.webp', $url);
        $this->assertStringContainsString('/studio/', $url);

        $product = Product::query()->with(['variants.prices', 'images'])->findOrFail($image->product_id);
        $card = app(StorefrontCatalog::class)->card($product, app(StoreSettings::class)->currency());
        $this->assertSame($url, $card['image']['url']);
    }

    public function test_reprocess_replaces_and_deletes_previous_file(): void
    {
        $image = $this->studioImage();
        $this->process($image);
        $old = $this->variantRow($image)->path;

        $this->process($image);

        $new = $this->variantRow($image)->path;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
        $this->assertSame(1, ImageVariant::query()->count());
    }

    public function test_reprocess_via_queue_deletes_previous_file(): void
    {
        $image = $this->studioImage();
        $this->process($image);
        $old = $this->variantRow($image)->path;

        Queue::fake();
        app(ImageStudio::class)->queue($image);
        $this->assertSame(ImageVariant::PENDING, $this->variantRow($image)->status);

        (new ProcessProductImage($image->id))->handle(app(ImageProcessor::class), app(ImageStudio::class));

        $row = $this->variantRow($image);
        $this->assertSame(ImageVariant::DONE, $row->status);
        $this->assertNotSame($old, $row->path);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($row->path);
    }

    public function test_skipped_rerun_deletes_stale_file_and_clears_path(): void
    {
        $image = $this->studioImage();
        $this->process($image);
        $old = $this->variantRow($image)->path;
        // Replace the original with noise so the rerun is skipped.
        Storage::disk('public')->put($image->path, $this->noiseBytes());

        $this->process($image);

        $row = $this->variantRow($image);
        $this->assertSame(ImageVariant::SKIPPED, $row->status);
        $this->assertNull($row->path);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_failed_rerun_deletes_stale_file_and_clears_path(): void
    {
        $image = $this->studioImage();
        $this->process($image);
        $old = $this->variantRow($image)->path;
        Storage::disk('public')->put($image->path, 'not an image');

        try {
            $this->process($image);
        } catch (RuntimeException) {
        }

        $row = $this->variantRow($image);
        $this->assertSame(ImageVariant::FAILED, $row->status);
        $this->assertNull($row->path);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_soft_deleting_product_keeps_variant(): void
    {
        $image = $this->studioImage();
        $this->process($image);
        $path = $this->variantRow($image)->path;

        Product::query()->findOrFail($image->product_id)->delete();

        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($this->variantRow($image));
    }

    private function noiseBytes(): string
    {
        $c = imagecreatetruecolor(64, 64);
        mt_srand(7);
        for ($x = 0; $x < 64; $x++) {
            for ($y = 0; $y < 64; $y++) {
                imagesetpixel($c, $x, $y, imagecolorallocate($c, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        ob_start();
        imagepng($c);

        return (string) ob_get_clean();
    }

    public function test_skipped_image_keeps_serving_original(): void
    {
        $image = $this->noisy();

        $this->process($image);

        $row = $this->variantRow($image);
        $this->assertSame(ImageVariant::SKIPPED, $row->status);
        $this->assertNotEmpty($row->error);
        $this->assertSame(Storage::disk('public')->url($image->path), $image->fresh()->url());
    }

    public function test_failure_marks_row_failed_with_limited_message(): void
    {
        $image = $this->studioImage();
        $this->app->bind(ImageProcessor::class, fn () => new class extends ImageProcessor
        {
            public function __construct() {}

            public function process(ProductImage $image): ProcessResult
            {
                throw new RuntimeException(str_repeat('x', 900));
            }
        });

        try {
            $this->process($image);
            $this->fail('Expected the job to rethrow for retry');
        } catch (RuntimeException) {
            // rethrown so the queue can retry
        }

        $row = $this->variantRow($image);
        $this->assertSame(ImageVariant::FAILED, $row->status);
        $this->assertLessThanOrEqual(500, strlen($row->error));
    }

    public function test_missing_image_is_ignored(): void
    {
        ProcessProductImage::dispatchSync(999999);

        $this->assertSame(0, ImageVariant::query()->count());
    }

    public function test_revert_restores_original_and_deletes_file(): void
    {
        $image = $this->studioImage();
        $this->process($image);
        $row = $this->variantRow($image);
        $path = $row->path;

        app(ImageStudio::class)->revert($row);
        $this->app->forgetInstance(VariantUrls::class);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->variantRow($image));
        $this->assertSame(Storage::disk('public')->url($image->path), $image->fresh()->url());
    }

    public function test_queue_many_stats_and_revert_all(): void
    {
        Queue::fake();
        $a = $this->studioImage();
        $b = $this->studioImage();
        $studio = app(ImageStudio::class);

        $this->assertSame(2, $studio->queueMany([$a, $b]));
        $this->assertSame(2, $studio->stats()['pending']);

        ImageVariant::query()->where('product_image_id', $a->id)->update(['status' => 'done', 'bytes_before' => 1000, 'bytes_after' => 400]);
        $stats = $studio->stats();
        $this->assertSame(1, $stats['done']);
        $this->assertSame(600, $stats['bytes_saved']);

        $this->assertSame(2, $studio->revertAll());
        $this->assertSame(0, ImageVariant::query()->count());
    }

    public function test_enabled_setting_off_serves_originals(): void
    {
        $image = $this->studioImage();
        $this->process($image);
        $this->assertStringEndsWith('.webp', $image->fresh()->url());

        $this->setting('enabled', false);

        $this->assertSame(Storage::disk('public')->url($image->path), $image->fresh()->url());
    }

    public function test_disabled_module_serves_originals(): void
    {
        $image = $this->studioImage();
        $this->process($image);

        app(ModuleManager::class)->disable(ImageStudioServiceProvider::MODULE);
        $this->app->forgetInstance(ProductImageUrls::class);

        $this->assertNotContains(ImageStudioServiceProvider::MODULE, array_keys(app(ModuleRepository::class)->enabledForBoot()));
        $this->assertSame(Storage::disk('public')->url($image->path), $image->fresh()->url());
    }

    public function test_deleting_product_image_removes_variant_file(): void
    {
        $image = $this->studioImage();
        $this->process($image);
        $path = $this->variantRow($image)->path;

        $image->delete();

        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, ImageVariant::query()->count());
    }

    public function test_variant_lookup_is_one_query_for_many_images(): void
    {
        $images = [];
        for ($i = 0; $i < 10; $i++) {
            $images[$i] = $this->studioImage('jpg', 60, 60, function (\GdImage $c): void {
                imagefilledrectangle($c, 20, 20, 39, 39, imagecolorallocate($c, 0x2B, 0x4C, 0x7E));
            });
            $this->process($images[$i]);
        }
        $this->assertSame(10, ImageVariant::query()->where('status', 'done')->count());

        $this->app->forgetInstance(VariantUrls::class);
        DB::enableQueryLog();
        foreach ($images as $image) {
            $this->assertStringEndsWith('.webp', $image->url());
        }
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'image_studio_variants'));

        $this->assertCount(1, $queries);
        $this->assertLessThanOrEqual(2, count(DB::getQueryLog()), 'url() must not query per image');
    }

    public function test_resolver_is_exception_safe(): void
    {
        $image = $this->studioImage();
        $this->process($image);
        $this->app->forgetInstance(VariantUrls::class);
        Schema::drop('image_studio_variants');

        $this->assertSame(Storage::disk('public')->url($image->path), $image->fresh()->url());
    }

    /**
     * Swaps in a processor that runs $during (simulating an admin action mid-job),
     * then writes a real variant file and returns it.
     */
    private function processorDoing(\Closure $during): \stdClass
    {
        $box = new \stdClass;
        $box->written = null;
        $this->app->bind(ImageProcessor::class, fn () => new class($during, $box) extends ImageProcessor
        {
            public function __construct(private \Closure $during, private \stdClass $box) {}

            public function process(ProductImage $image): ProcessResult
            {
                ($this->during)();
                $this->box->written = "products/{$image->product_id}/studio/".bin2hex(random_bytes(8)).'.webp';
                Storage::disk($image->disk)->put($this->box->written, 'webp-bytes');

                return new ProcessResult($this->box->written, 'gd-edge', 10, 5, 1, 1);
            }
        });

        return $box;
    }

    public function test_revert_while_pending_does_not_resurrect_the_variant(): void
    {
        $image = $this->studioImage();
        Queue::fake();
        $studio = app(ImageStudio::class);
        $studio->queue($image);

        $studio->revert($this->variantRow($image));
        (new ProcessProductImage($image->id))->handle(app(ImageProcessor::class), $studio);

        $this->assertNull($this->variantRow($image));
        $this->assertSame([], Storage::disk('public')->allFiles("products/{$image->product_id}/studio"));
        $this->assertSame(Storage::disk('public')->url($image->path), $image->fresh()->url());
    }

    public function test_revert_while_processing_leaves_no_orphan_file(): void
    {
        $image = $this->studioImage();
        $studio = app(ImageStudio::class);
        $studio->markPending($image);
        $box = $this->processorDoing(fn () => $studio->revert($this->variantRow($image)));

        ProcessProductImage::dispatchSync($image->id);

        $this->assertNotNull($box->written);
        $this->assertNull($this->variantRow($image));
        Storage::disk('public')->assertMissing($box->written);
    }

    public function test_deleting_product_image_mid_job_leaves_no_orphan_file(): void
    {
        $image = $this->studioImage();
        app(ImageStudio::class)->markPending($image);
        $box = $this->processorDoing(fn () => ProductImage::query()->whereKey($image->id)->firstOrFail()->delete());

        ProcessProductImage::dispatchSync($image->id);

        $this->assertNotNull($box->written);
        $this->assertSame(0, ImageVariant::query()->count());
        Storage::disk('public')->assertMissing($box->written);
    }

    public function test_revert_then_reprocess_while_processing_still_ends_done(): void
    {
        $image = $this->studioImage();
        $studio = app(ImageStudio::class);
        $studio->markPending($image);
        $first = true;
        $box = $this->processorDoing(function () use (&$first, $studio, $image): void {
            if ($first) {
                $first = false;
                $studio->revert($this->variantRow($image));
                $studio->markPending($image);
            }
        });

        ProcessProductImage::dispatchSync($image->id);

        $row = $this->variantRow($image);
        $this->assertSame(ImageVariant::DONE, $row->status);
        $this->assertSame($box->written, $row->path);
        Storage::disk('public')->assertExists($row->path);
        $this->assertCount(1, Storage::disk('public')->allFiles("products/{$image->product_id}/studio"));
    }
}
