<?php

namespace Tests\Support;

use App\Core\Extensions\ModuleManager;
use App\Domains\Catalog\Models\ProductImage;
use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Installs the image-studio module and builds real product images for it.
 * Requires the using test class to also use CommerceFixtures and ModuleFixtures.
 */
trait ImageStudioFixtures
{
    protected const STUDIO_MODULE = 'modrixcommerce/image-studio';

    protected function bootImageStudio(): void
    {
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules'), $this->modulesPath);
        $modules = app(ModuleManager::class);
        $modules->install(self::STUDIO_MODULE);
        $modules->enable(self::STUDIO_MODULE, true);
        config(['inertia.ssr.enabled' => false]);
        Storage::fake('public');
    }

    /**
     * A product image on the fake public disk: #F4F2EF background with, by
     * default, a #2B4C7E 100x100 square in the centre. The row is created
     * without model events so upload triggers do not fire.
     *
     * @param  Closure(\GdImage, int, int): void|null  $draw  replaces the default square
     */
    protected function studioImage(string $format = 'jpg', int $w = 200, int $h = 200, ?Closure $draw = null): ProductImage
    {
        $product = $this->sellable()->product;

        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 0xF4, 0xF2, 0xEF));

        if ($draw !== null) {
            $draw($canvas, $w, $h);
        } else {
            $x = intdiv($w - 100, 2);
            $y = intdiv($h - 100, 2);
            imagefilledrectangle($canvas, $x, $y, $x + 99, $y + 99, imagecolorallocate($canvas, 0x2B, 0x4C, 0x7E));
        }

        ob_start();
        match ($format) {
            'png' => imagepng($canvas),
            'webp' => imagewebp($canvas),
            default => imagejpeg($canvas, null, 95),
        };
        $bytes = (string) ob_get_clean();

        $path = "products/{$product->id}/".Str::random(20).'.'.($format === 'jpg' ? 'jpg' : $format);
        Storage::disk('public')->put($path, $bytes);

        return ProductImage::withoutEvents(fn () => $product->images()->create([
            'disk' => 'public',
            'path' => $path,
            'width' => $w,
            'height' => $h,
            'position' => 0,
        ]));
    }

    protected function pixelAlpha(string $bytes, int $x, int $y): int
    {
        $image = imagecreatefromstring($bytes);

        return (imagecolorat($image, $x, $y) >> 24) & 0x7F;
    }
}
