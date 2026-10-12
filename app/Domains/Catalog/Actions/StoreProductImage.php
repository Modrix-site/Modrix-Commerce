<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores an uploaded product image.
 *
 * Uploaded bytes are never stored as-is: the image is decoded with GD and
 * re-encoded, which strips metadata (EXIF location, comments) and any
 * appended payloads (polyglot files). The stored file name is random and the
 * extension is derived from the detected type, never from user input.
 */
final class StoreProductImage
{
    public const MAX_PIXELS = 40_000_000;

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Product $product, UploadedFile $file, ?string $alt = null): ProductImage
    {
        $info = @getimagesize($file->getRealPath() ?: '');

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['image' => 'Upload a JPEG, PNG or WebP image.']);
        }

        [$width, $height] = $info;

        if ($width * $height > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['image' => 'The image dimensions are too large.']);
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath() ?: ''));

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['image' => 'The image could not be read.']);
        }

        [$extension, $bytes] = $this->encode($image, $info[2]);
        imagedestroy($image);

        $disk = (string) config('ecomodular.media_disk', 'public');
        $path = "products/{$product->id}/".Str::random(40).".{$extension}";
        Storage::disk($disk)->put($path, $bytes, ['visibility' => 'public']);

        $image = $product->images()->create([
            'disk' => $disk,
            'path' => $path,
            'alt' => $alt ?: $product->name,
            'position' => (int) $product->images()->max('position') + 1,
            'width' => $width,
            'height' => $height,
        ]);

        $this->audit->record('product.image_added', $product, ['image_id' => $image->id]);

        return $image;
    }

    /**
     * @return array{string, string}
     */
    private function encode(GdImage $image, int $type): array
    {
        ob_start();

        $extension = match ($type) {
            IMAGETYPE_PNG => (function () use ($image) {
                imagesavealpha($image, true);
                imagepng($image, null, 6);

                return 'png';
            })(),
            IMAGETYPE_WEBP => (function () use ($image) {
                imagesavealpha($image, true);
                imagewebp($image, null, 85);

                return 'webp';
            })(),
            default => (function () use ($image) {
                imagejpeg($image, null, 85);

                return 'jpg';
            })(),
        };

        return [$extension, (string) ob_get_clean()];
    }
}
