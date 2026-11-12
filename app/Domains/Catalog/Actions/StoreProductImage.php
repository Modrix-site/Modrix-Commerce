<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Core\Media\ImageSanitizer;
use App\Core\Media\Media;
use App\Core\Media\MediaLibrary;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores an uploaded product image, re-encoded by the ImageSanitizer (never
 * the uploaded bytes) under a random name.
 */
final class StoreProductImage
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ImageSanitizer $sanitizer,
        private readonly MediaLibrary $library,
    ) {}

    public function handle(Product $product, UploadedFile $file, ?string $alt = null): ProductImage
    {
        $clean = $this->sanitizer->sanitize($file->getRealPath() ?: '');

        $disk = (string) config('ecomodular.media_disk', 'public');
        $path = "products/{$product->id}/".Str::random(40).".{$clean['extension']}";
        Storage::disk($disk)->put($path, $clean['bytes'], ['visibility' => 'public']);

        $image = $product->images()->create([
            'disk' => $disk,
            'path' => $path,
            'alt' => $alt ?: $product->name,
            'position' => (int) $product->images()->max('position') + 1,
            'width' => $clean['width'],
            'height' => $clean['height'],
        ]);

        // Listed in the media library so it can be reused.
        $this->library->register($disk, $path, Media::PRODUCTS, $file->getClientOriginalName(), $clean['width'], $clean['height'], strlen($clean['bytes']), $image->alt);

        $this->audit->record('product.image_added', $product, ['image_id' => $image->id]);

        return $image;
    }
}
