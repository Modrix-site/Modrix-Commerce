<?php

namespace App\Core\Media;

use GdImage;
use Illuminate\Validation\ValidationException;

/**
 * Decodes an uploaded image and re-encodes it.
 *
 * Uploaded bytes are never stored as-is: re-encoding strips metadata (EXIF
 * location, comments) and appended payloads (polyglot files). The extension
 * comes from the detected type, never from user input. SVG is not accepted
 * because it can carry scripts.
 */
final class ImageSanitizer
{
    public const MAX_PIXELS = 40_000_000;

    /**
     * @return array{extension: string, bytes: string, width: int, height: int}
     *
     * @throws ValidationException
     */
    public function sanitize(string $path, string $field = 'image'): array
    {
        $info = @getimagesize($path);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages([$field => 'Upload a JPEG, PNG or WebP image.']);
        }

        [$width, $height] = $info;

        if ($width * $height > self::MAX_PIXELS) {
            throw ValidationException::withMessages([$field => 'The image dimensions are too large.']);
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([$field => 'The image could not be read.']);
        }

        [$extension, $bytes] = $this->encode($image, $info[2]);
        imagedestroy($image);

        return ['extension' => $extension, 'bytes' => $bytes, 'width' => $width, 'height' => $height];
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
