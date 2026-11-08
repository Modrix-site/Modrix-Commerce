<?php

namespace EcoModular\ImageStudio\Services;

use App\Core\Media\ImageSanitizer;
use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ImageStudio\Removers\RemoverFactory;
use EcoModular\ImageStudio\Support\ImageStudioSettings;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Decodes a product image, optionally removes its background and stores a WebP
 * variant next to the original. Never touches the original file or its row.
 */
class ImageProcessor
{
    public const MAX_SIDE = 2400;

    public function __construct(
        private readonly ImageStudioSettings $settings,
        private readonly RemoverFactory $removers,
    ) {}

    public function process(ProductImage $image): ProcessResult
    {
        $disk = Storage::disk($image->disk);

        if (! $disk->exists($image->path)) {
            throw new RuntimeException('Original image file is missing');
        }

        $bytes = $disk->get($image->path);
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Original image file is missing');
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            throw new RuntimeException('Image could not be decoded');
        }
        if ($info[0] * $info[1] > ImageSanitizer::MAX_PIXELS) {
            throw new RuntimeException('The image dimensions are too large');
        }

        $decoded = @imagecreatefromstring($bytes);
        if (! $decoded instanceof GdImage) {
            throw new RuntimeException('Image could not be decoded');
        }

        $working = $this->prepare($decoded);
        unset($decoded);

        $driver = 'none';
        if ($this->settings->removeBackground()) {
            $remover = $this->removers->make($this->settings->driver());
            $driver = $this->settings->driver();
            $working = $remover->remove($working, $bytes);
        }

        imagealphablending($working, false);
        imagesavealpha($working, true);

        ob_start();
        $ok = imagewebp($working, null, $this->settings->webpQuality());
        $webp = (string) ob_get_clean();
        if (! $ok || $webp === '') {
            throw new RuntimeException('Image could not be encoded as WebP');
        }

        $width = imagesx($working);
        $height = imagesy($working);
        unset($working);

        $path = "products/{$image->product_id}/studio/".Str::random(40).'.webp';
        if (! $disk->put($path, $webp, ['visibility' => 'public'])) {
            throw new RuntimeException('Processed image could not be stored');
        }

        return new ProcessResult($path, $driver, strlen($bytes), strlen($webp), $width, $height);
    }

    /** A true-colour, alpha-preserving copy, downscaled so the longer side is at most MAX_SIDE. */
    private function prepare(GdImage $source): GdImage
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min(1.0, self::MAX_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($nw, $nh);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $canvas;
    }
}
