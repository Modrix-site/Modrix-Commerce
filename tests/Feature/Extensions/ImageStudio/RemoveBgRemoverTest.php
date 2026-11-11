<?php

namespace Tests\Feature\Extensions\ImageStudio;

use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ImageStudio\Jobs\ProcessProductImage;
use EcoModular\ImageStudio\Models\ImageVariant;
use EcoModular\ImageStudio\Services\ImageStudio;
use EcoModular\ImageStudio\Support\ImageStudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CommerceFixtures;
use Tests\Support\ImageStudioFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;
use Throwable;

class RemoveBgRemoverTest extends TestCase
{
    use CommerceFixtures, ImageStudioFixtures, ModuleFixtures, RefreshDatabase;

    private const KEY = 'rb-secret-key-12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootImageStudio();
        app(SettingsRepository::class)->setMany(['extension.modrixcommerce.image-studio.driver' => 'removebg']);
    }

    /** A transparent PNG with an opaque square, at the given size. */
    private function transparentPng(int $w = 200, int $h = 200): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, (int) imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagefilledrectangle($img, intdiv($w, 4), intdiv($h, 4), intdiv($w * 3, 4), intdiv($h * 3, 4), (int) imagecolorallocatealpha($img, 200, 30, 30, 0));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    private function row(int $imageId): ImageVariant
    {
        return ImageVariant::query()->where('product_image_id', $imageId)->firstOrFail();
    }

    private function runJob(int $id): ?Throwable
    {
        try {
            app(ImageStudio::class)->markPending(ProductImage::query()->findOrFail($id));
            ProcessProductImage::dispatchSync($id);
        } catch (Throwable $e) {
            return $e;
        }

        return null;
    }

    public function test_removebg_success_stores_variant(): void
    {
        app(ImageStudioSettings::class)->setRemoveBgKey(self::KEY);
        Http::fake(['api.remove.bg/*' => Http::response($this->transparentPng(), 200)]);
        $image = $this->studioImage();

        $this->assertNull($this->runJob($image->id));

        $row = $this->row($image->id);
        $this->assertSame(ImageVariant::DONE, $row->status);
        $this->assertSame('removebg', $row->driver);
        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Api-Key', self::KEY)
            && $r->url() === 'https://api.remove.bg/v1.0/removebg');
        $bytes = Storage::disk('public')->get($row->path);
        $this->assertSame(127, $this->pixelAlpha($bytes, 2, 2));
        $this->assertSame(0, $this->pixelAlpha($bytes, 100, 100));
    }

    public function test_removebg_result_is_scaled_to_the_working_size(): void
    {
        app(ImageStudioSettings::class)->setRemoveBgKey(self::KEY);
        Http::fake(['api.remove.bg/*' => Http::response($this->transparentPng(400, 400), 200)]);
        $image = $this->studioImage();

        $this->runJob($image->id);

        $row = $this->row($image->id);
        $this->assertSame(200, $row->width);
        $this->assertSame(200, $row->height);
        $this->assertSame(0, $this->pixelAlpha(Storage::disk('public')->get($row->path), 100, 100));
    }

    public function test_removebg_402_marks_failed_without_leaking_key(): void
    {
        app(ImageStudioSettings::class)->setRemoveBgKey(self::KEY);
        Http::fake(['api.remove.bg/*' => Http::response(['errors' => [['title' => 'Insufficient credits '.self::KEY]]], 402)]);
        $image = $this->studioImage();

        $this->assertNotNull($this->runJob($image->id));

        $row = $this->row($image->id);
        $this->assertSame(ImageVariant::FAILED, $row->status);
        $this->assertStringContainsString('402', $row->error);
        $this->assertStringNotContainsString(self::KEY, $row->error);
    }

    public function test_removebg_connection_error_has_generic_message(): void
    {
        app(ImageStudioSettings::class)->setRemoveBgKey(self::KEY);
        Http::fake(fn () => throw new ConnectionException('cURL error with '.self::KEY));
        $image = $this->studioImage();

        $e = $this->runJob($image->id);

        $this->assertNotNull($e);
        $row = $this->row($image->id);
        $this->assertSame(ImageVariant::FAILED, $row->status);
        $this->assertStringContainsString('connection error', $row->error);
        $this->assertStringNotContainsString(self::KEY, $row->error);
        $this->assertStringNotContainsString(self::KEY, $e->getMessage());
    }

    public function test_removebg_without_key_fails_and_sends_nothing(): void
    {
        Http::fake();
        $image = $this->studioImage();

        $this->assertNotNull($this->runJob($image->id));

        $row = $this->row($image->id);
        $this->assertSame(ImageVariant::FAILED, $row->status);
        $this->assertStringContainsString('API key is not set', $row->error);
        Http::assertNothingSent();
    }
}
