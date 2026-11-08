<?php

namespace App\Core\Updates;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * Core release packages: a zip of the managed paths (config
 * `ecomodular.updates.paths`), entries relative to the app root.
 * Builds them (`ecomodular:core-package`), backs up the current code the
 * same way, and extracts downloaded ones with every entry checked first.
 */
final class CorePackage
{
    public const VERSION_PATTERN = "/const\s+VERSION\s*=\s*'([^']+)'/";

    private const MAX_ENTRIES = 200_000;

    /**
     * Builds `modrix-commerce-<version>.zip` from the tree at $root.
     * $output is a directory, or a file path ending in `.zip`.
     *
     * @return string the zip path
     *
     * @throws CoreUpdateError
     */
    public function build(string $root, string $output): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');

        foreach (['vendor/autoload.php' => 'run composer install --no-dev first', 'public/build/manifest.json' => 'run npm run build first'] as $required => $hint) {
            if (! is_file($root.'/'.$required)) {
                throw new CoreUpdateError("{$required} is missing: {$hint}.");
            }
        }

        $version = self::versionIn($root) ?? throw new CoreUpdateError('app/Core/Platform.php does not declare a version.');
        $zipPath = str_ends_with(strtolower($output), '.zip') ? $output : rtrim($output, '/\\').'/modrix-commerce-'.$version.'.zip';
        File::ensureDirectoryExists(dirname($zipPath));

        $this->zipManagedPaths($root, $zipPath);

        return $zipPath;
    }

    /**
     * Zips every managed path under $root (also used for the code backup).
     *
     * @throws CoreUpdateError
     */
    public function zipManagedPaths(string $root, string $zipPath): void
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new CoreUpdateError('Cannot create '.basename($zipPath).'.');
        }

        foreach (self::managedPaths() as $path) {
            $full = $root.'/'.$path;

            if (is_link($full)) {
                continue;
            }

            if (is_file($full)) {
                $zip->addFile($full, $path);

                continue;
            }

            if (! is_dir($full)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, RecursiveDirectoryIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }

                $name = $path.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($full) + 1));

                if ($this->excluded($name) || ! self::safeEntry($name)) {
                    continue;
                }

                $zip->addFile($file->getPathname(), $name);
            }
        }

        if (! $zip->close()) {
            @unlink($zipPath);

            throw new CoreUpdateError('Writing '.basename($zipPath).' failed.');
        }
    }

    /**
     * Checks every entry (names, symlinks, count, total size) before writing
     * anything, then extracts into $target.
     *
     * @throws CoreUpdateError
     */
    public function extract(string $zipPath, string $target, int $maxBytes): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new CoreUpdateError('The package is not a readable zip archive.');
        }

        try {
            if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new CoreUpdateError('The package has an unexpected number of entries.');
            }

            $total = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = is_array($stat) ? (string) $stat['name'] : '';

                if (! self::safeEntry($name)) {
                    throw new CoreUpdateError('The package contains an unsafe path and was refused.');
                }

                if (self::isDatabaseFile($name)) {
                    throw new CoreUpdateError('The package contains a database file ('.basename($name).') and was refused.');
                }

                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
                    && $opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new CoreUpdateError('The package contains an unsafe symbolic link and was refused.');
                }

                $total += (int) ($stat['size'] ?? 0);

                if ($total > $maxBytes) {
                    throw new CoreUpdateError('The package is too large when extracted.');
                }
            }

            File::ensureDirectoryExists($target, 0700);
            $written = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $path = $target.'/'.$name;

                if (str_ends_with($name, '/')) {
                    File::ensureDirectoryExists($path);

                    continue;
                }

                $expected = (int) (($zip->statIndex($i) ?: [])['size'] ?? -1);
                File::ensureDirectoryExists(dirname($path));
                $in = $zip->getStreamIndex($i);
                // Suppressed: the warning would carry the absolute path; the error below does not.
                $out = $in === false ? false : @fopen($path, 'xb');

                if ($in === false || $out === false) {
                    if (is_resource($in)) {
                        fclose($in);
                    }

                    throw new CoreUpdateError('The package entry '.$name.' could not be extracted.');
                }

                $entry = 0;
                $closed = false;

                try {
                    // Never trust the sizes in the header: count what is written.
                    while (! feof($in)) {
                        $chunk = fread($in, 1_048_576);

                        if ($chunk === false) {
                            throw new CoreUpdateError('The package entry '.$name.' could not be read.');
                        }

                        $written += strlen($chunk);
                        $entry += strlen($chunk);

                        if ($written > $maxBytes) {
                            throw new CoreUpdateError('The package is too large when extracted.');
                        }

                        if ($chunk !== '' && @fwrite($out, $chunk) !== strlen($chunk)) {
                            throw new CoreUpdateError('The package entry '.$name.' could not be written (is the disk full?).');
                        }
                    }

                    $closed = true;
                    if (! @fclose($out)) {
                        throw new CoreUpdateError('The package entry '.$name.' could not be written (is the disk full?).');
                    }
                } finally {
                    fclose($in);
                    if (! $closed) {
                        @fclose($out);
                    }
                }

                if ($entry !== $expected) {
                    throw new CoreUpdateError('The package entry '.$name.' is damaged (size mismatch).');
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * A relative path with no `.`/`..`/empty segments, backslashes, drive
     * letters, colons or control characters. A trailing `/` (directory) is allowed.
     */
    public static function safeEntry(string $name): bool
    {
        if ($name === '' || strlen($name) > 1024 || str_starts_with($name, '/')
            || str_contains($name, '\\') || str_contains($name, ':') || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            return false;
        }

        foreach (explode('/', str_ends_with($name, '/') ? substr($name, 0, -1) : $name) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /** The version declared by `app/Core/Platform.php` under $root. */
    public static function versionIn(string $root): ?string
    {
        $file = rtrim($root, '/\\').'/app/Core/Platform.php';

        if (! is_file($file) || ! preg_match(self::VERSION_PATTERN, (string) file_get_contents($file), $m)) {
            return null;
        }

        return $m[1];
    }

    /** @return list<string> */
    public static function managedPaths(): array
    {
        return array_values(array_filter(
            (array) config('ecomodular.updates.paths', []),
            fn ($path) => is_string($path) && self::safeEntry($path) && ! str_ends_with($path, '/'),
        ));
    }

    /**
     * SQLite databases and their journals (`*.sqlite`, `*.sqlite3`, `*.db`,
     * `*-journal`, `*-wal`, `*-shm`): never packaged, never installed.
     */
    public static function isDatabaseFile(string $name): bool
    {
        return preg_match('/\.(sqlite|sqlite3|db)$|-(journal|wal|shm)$/i', basename(rtrim($name, '/'))) === 1;
    }

    /** Never shipped even inside a managed path. */
    private function excluded(string $name): bool
    {
        if (in_array('.git', explode('/', $name), true) || self::isDatabaseFile($name)) {
            return true;
        }

        $base = basename($name);

        return str_starts_with($base, '.env') && $base !== '.env.example';
    }
}
