<?php

namespace Tests\Feature\Extensions\ImageStudio;

use App\Core\Settings\SettingsRepository;
use EcoModular\ImageStudio\Services\ImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\CommerceFixtures;
use Tests\Support\ImageStudioFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class ImageProcessorTest extends TestCase
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

    public function test_produces_webp_with_alpha(): void
    {
        $image = $this->studioImage('jpg');
        $before = Storage::disk('public')->get($image->path);

        $result = app(ImageProcessor::class)->process($image);

        $bytes = Storage::disk('public')->get($result->path);
        $this->assertSame('RIFF', substr($bytes, 0, 4));
        $this->assertSame('WEBP', substr($bytes, 8, 4));
        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring($bytes)[2]);
        $this->assertSame(127, $this->pixelAlpha($bytes, 0, 0));
        $this->assertSame(0, $this->pixelAlpha($bytes, 100, 100));
        $this->assertMatchesRegularExpression('#^products/\d+/studio/[A-Za-z0-9]{40}\.webp$#', $result->path);
        $this->assertSame('gd-edge', $result->driver);
        $this->assertSame(strlen($bytes), $result->bytesAfter);
        $this->assertSame(strlen($before), $result->bytesBefore);
        $this->assertSame([200, 200], [$result->width, $result->height]);
        $this->assertSame($before, Storage::disk('public')->get($image->path));
    }

    public function test_webp_only_mode_keeps_pixels_opaque(): void
    {
        $this->setting('remove_background', false);
        $result = app(ImageProcessor::class)->process($this->studioImage('jpg'));

        $this->assertSame('none', $result->driver);
        $this->assertSame(0, $this->pixelAlpha(Storage::disk('public')->get($result->path), 0, 0));
    }

    public function test_large_image_is_downscaled_to_2400(): void
    {
        $this->setting('remove_background', false);
        $result = app(ImageProcessor::class)->process($this->studioImage('jpg', 3000, 1500));

        $this->assertSame([2400, 1200], [$result->width, $result->height]);
    }

    public function test_transparent_png_keeps_alpha(): void
    {
        $this->setting('remove_background', false);
        $image = $this->studioImage('png', 200, 200, function (\GdImage $c, int $w, int $h): void {
            imagealphablending($c, false);
            imagesavealpha($c, true);
            imagefill($c, 0, 0, (int) imagecolorallocatealpha($c, 0, 0, 0, 127));
            imagefilledrectangle($c, 50, 50, 149, 149, (int) imagecolorallocatealpha($c, 200, 0, 0, 0));
        });

        $result = app(ImageProcessor::class)->process($image);
        $bytes = Storage::disk('public')->get($result->path);

        $this->assertSame(127, $this->pixelAlpha($bytes, 0, 0));
        $this->assertSame(0, $this->pixelAlpha($bytes, 100, 100));
    }

    public function test_downscaled_image_keeps_alpha(): void
    {
        $this->setting('remove_background', false);
        $image = $this->studioImage('png', 3000, 1500, function (\GdImage $c, int $w, int $h): void {
            imagealphablending($c, false);
            imagesavealpha($c, true);
            imagefill($c, 0, 0, (int) imagecolorallocatealpha($c, 0, 0, 0, 127));
        });

        $result = app(ImageProcessor::class)->process($image);

        $this->assertSame(127, $this->pixelAlpha(Storage::disk('public')->get($result->path), 5, 5));
    }

    public function test_missing_original_throws(): void
    {
        $image = $this->studioImage('jpg');
        Storage::disk('public')->delete($image->path);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Original image file is missing');
        app(ImageProcessor::class)->process($image);
    }

    public function test_undecodable_file_throws(): void
    {
        $image = $this->studioImage('jpg');
        Storage::disk('public')->put($image->path, 'not an image');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Image could not be decoded');
        app(ImageProcessor::class)->process($image);
    }
}
