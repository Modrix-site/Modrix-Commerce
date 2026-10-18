<?php

namespace App\Core\Marketplace;

use App\Core\Authorization\PermissionRegistry;
use App\Core\Extensions\Manifest;
use App\Core\Extensions\ManifestValidator;
use App\Core\Extensions\ValidationContext;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeManifestValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Reads and extracts extension packages (.zip) defensively. Used by the
 * server when a release is uploaded and by stores before installing one.
 *
 * Refused: paths with "..", absolute or drive paths, backslashes, NUL
 * bytes, symbolic links, more than MAX_FILES entries, more than
 * MAX_UNCOMPRESSED bytes in total, and nested directories outside the
 * package root. The manifest (module.json or theme.json) must be at the
 * archive root and must validate with the platform's own validators.
 * Entries are written one by one; ZipArchive::extractTo is never used.
 */
final class PackageArchive
{
    public const MAX_FILES = 5000;

    public const MAX_UNCOMPRESSED = 209_715_200; // 200 MB

    /**
     * Inspects and extracts a package into a new temporary directory.
     *
     * @param  string  $type  "module" or "theme"
     * @return array{dir: string, manifest: Manifest|ThemeManifest}
     *
     * @throws PackageException
     */
    public static function extract(string $zipPath, string $type, string $expectedName): array
    {
        if (! in_array($type, ['module', 'theme'], true)) {
            throw new PackageException("Unsupported package type {$type}.");
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new PackageException('The package is not a readable zip archive.');
        }

        $dir = storage_path('app/private/marketplace/tmp/'.Str::random(32));

        try {
            if ($zip->numFiles > self::MAX_FILES) {
                throw new PackageException('The package contains too many files.');
            }

            $total = 0;
            $entries = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = is_array($stat) ? (string) $stat['name'] : '';

                self::assertSafeName($name);

                $zip->getExternalAttributesIndex($i, $os, $attributes);
                if ($os === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new PackageException("The package contains a symbolic link ({$name}).");
                }

                $total += (int) ($stat['size'] ?? 0);
                if ($total > self::MAX_UNCOMPRESSED) {
                    throw new PackageException('The package is too large when unpacked.');
                }

                $entries[$i] = $name;
            }

            File::ensureDirectoryExists($dir);

            foreach ($entries as $i => $name) {
                $target = $dir.'/'.$name;

                if (str_ends_with($name, '/')) {
                    File::ensureDirectoryExists($target);

                    continue;
                }

                File::ensureDirectoryExists(dirname($target));
                $in = $zip->getStream($name);
                $out = fopen($target, 'wb');

                if ($in === false || $out === false) {
                    throw new PackageException("Could not unpack {$name}.");
                }

                // Copy with a hard cap, in case the declared sizes lie.
                $written = stream_copy_to_stream($in, $out, self::MAX_UNCOMPRESSED + 1);
                fclose($in);
                fclose($out);

                if ($written === false || $written > self::MAX_UNCOMPRESSED) {
                    throw new PackageException('The package is too large when unpacked.');
                }
            }

            $manifest = self::validate($dir, $type, $expectedName);
        } catch (PackageException $e) {
            File::deleteDirectory($dir);

            throw $e;
        } finally {
            $zip->close();
        }

        return ['dir' => $dir, 'manifest' => $manifest];
    }

    /**
     * Builds a zip from a package directory (used by tests and the
     * owner's packaging command).
     */
    public static function create(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $base = rtrim(str_replace('\\', '/', (string) realpath($sourceDir)), '/');

        foreach (File::allFiles($sourceDir) as $file) {
            $path = str_replace('\\', '/', $file->getRealPath() ?: $file->getPathname());
            $zip->addFile($path, ltrim(substr($path, strlen($base)), '/'));
        }

        $zip->close();
    }

    private static function assertSafeName(string $name): void
    {
        if ($name === '' || strlen($name) > 255 || str_contains($name, "\0") || str_contains($name, '\\')
            || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)
            || in_array('..', explode('/', $name), true)) {
            throw new PackageException('The package contains an unsafe path: '.Str::limit(preg_replace('/[^\x20-\x7E]/', '?', $name) ?? '', 80));
        }
    }

    /**
     * @throws PackageException
     */
    private static function validate(string $dir, string $type, string $expectedName): Manifest|ThemeManifest
    {
        $file = $dir.'/'.($type === 'module' ? Manifest::FILE : ThemeManifest::FILE);

        if (! is_file($file)) {
            throw new PackageException('The package has no '.basename($file).' at its root.');
        }

        $data = json_decode((string) file_get_contents($file), true, 32);

        if ($type === 'module') {
            $result = app(ManifestValidator::class)->validate($dir, $expectedName, $data, self::moduleContext());

            if ($result->manifest === null) {
                throw new PackageException('The module is invalid: '.implode(' ', $result->errors));
            }

            return $result->manifest;
        }

        [$manifest, $errors] = app(ThemeManifestValidator::class)->validate($dir, $expectedName, $data);

        if ($manifest === null) {
            throw new PackageException('The theme is invalid: '.implode(' ', $errors));
        }

        return $manifest;
    }

    /**
     * Core permissions and migration names, so a package may use them the
     * same way a module discovered on disk can.
     */
    private static function moduleContext(): ValidationContext
    {
        $permissions = [];

        foreach (app(PermissionRegistry::class)->all() as $definition) {
            if ($definition['source'] === 'core') {
                $permissions[$definition['name']] = 'core';
            }
        }

        $migrations = [];

        foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
            $migrations[basename($file)] = 'core';
        }

        return new ValidationContext(permissionOwners: $permissions, migrationOwners: $migrations);
    }
}
