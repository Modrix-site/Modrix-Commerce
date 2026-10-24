<?php

namespace Marketplace\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Checks and stores uploads in private storage. Package ZIPs are inspected
 * but never extracted or executed here; images must be real PNG/JPEG/WebP.
 */
class Uploads
{
    public const MAX_ZIP_BYTES = 20 * 1024 * 1024;

    public const MAX_UNPACKED_BYTES = 100 * 1024 * 1024;

    public const MAX_IMAGE_BYTES = 3 * 1024 * 1024;

    /**
     * A package ZIP whose root manifest names this identifier and version.
     * Returns the stored path relative to private storage.
     *
     * @throws ValidationException on the given field
     */
    public function packageZip(UploadedFile $file, string $identifier, string $type, string $version, string $field = 'package'): string
    {
        $fail = fn (string $message) => throw ValidationException::withMessages([$field => $message]);

        if (! $file->isValid() || $file->getSize() > self::MAX_ZIP_BYTES) {
            $fail('Upload a ZIP up to 20 MB.');
        }

        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            $fail('The package is not a valid ZIP archive.');
        }

        try {
            if ($zip->numFiles > 10000) {
                $fail('The package contains too many files.');
            }

            $bytes = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = str_replace('\\', '/', (string) $entry['name']);
                $bytes += (int) $entry['size'];
                $zip->getExternalAttributesIndex($i, $os, $attributes);
                $symlink = (($attributes >> 16) & 0170000) === 0120000;

                if ($symlink || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)|^[a-zA-Z]:#', $name) === 1 || $bytes > self::MAX_UNPACKED_BYTES) {
                    $fail('The package contains unsafe paths or is larger than 100 MB unpacked.');
                }
            }

            $manifestName = $type === 'theme' ? 'theme.json' : 'module.json';
            $stat = $zip->statName($manifestName);

            if ($stat === false || $stat['size'] > 65536) {
                $fail("Include {$manifestName} at the ZIP root (up to 64 KB).");
            }

            $manifest = json_decode((string) $zip->getFromName($manifestName), true);

            if (! is_array($manifest) || ($manifest['name'] ?? null) !== $identifier || ($manifest['version'] ?? null) !== $version) {
                $fail('The ZIP manifest package identifier and version must match the listing.');
            }
        } finally {
            $zip->close();
        }

        return $this->store($file, 'packages', 'zip');
    }

    /**
     * A PNG, JPEG or WebP image up to 3 MB and 8000 × 8000 pixels (no SVG).
     *
     * @throws ValidationException on the given field
     */
    public function image(UploadedFile $file, string $folder, string $field): string
    {
        $types = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $info = $file->isValid() && $file->getSize() <= self::MAX_IMAGE_BYTES ? @getimagesize($file->getRealPath()) : false;

        if (! is_array($info) || ! isset($types[$info['mime']]) || $info[0] > 8000 || $info[1] > 8000) {
            throw ValidationException::withMessages([$field => 'Use a PNG, JPEG or WebP image up to 3 MB and 8000 × 8000 pixels.']);
        }

        return $this->store($file, $folder, $types[$info['mime']]);
    }

    /** Absolute path of a stored private file. */
    public function path(string $relative): string
    {
        return rtrim((string) config('marketplace.storage'), '/\\').'/'.ltrim($relative, '/');
    }

    private function store(UploadedFile $file, string $folder, string $extension): string
    {
        $relative = $folder.'/'.Str::random(32).'.'.$extension;
        File::ensureDirectoryExists(dirname($this->path($relative)));
        File::copy($file->getRealPath(), $this->path($relative));

        return $relative;
    }
}
