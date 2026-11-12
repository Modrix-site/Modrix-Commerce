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

            // A ZIP of the package folder ("prism/theme.json") unpacks as if
            // the manifest were at the root: the folder name is stripped.
            $prefix = self::manifestPrefix($zip, $type === 'theme' ? 'theme.json' : 'module.json') ?? '';

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
                // Never part of a package: OS clutter that "compress" adds.
                if (self::isOsClutter($name)) {
                    continue;
                }

                if ($prefix !== '') {
                    // Outside the package folder there is only OS clutter
                    // (__MACOSX/, .DS_Store), which manifestPrefix allowed.
                    if (! str_starts_with($name, $prefix) || $name === $prefix) {
                        continue;
                    }
                }

                $target = $dir.'/'.substr($name, strlen($prefix));

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
     * Where the manifest sits: "" at the archive root, "<folder>/" when the
     * whole package is inside one top-level folder (how "compress this
     * folder" builds a ZIP), or null when it is in neither place.
     * macOS clutter (__MACOSX/, .DS_Store) is ignored.
     */
    public static function manifestPrefix(ZipArchive $zip, string $manifest): ?string
    {
        if ($zip->locateName($manifest) !== false) {
            return '';
        }

        $folders = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (self::isOsClutter($name)) {
                continue;
            }

            $slash = strpos($name, '/');

            if ($slash === false || $slash === 0) {
                return null; // A file at the root: not a single-folder ZIP.
            }

            $folders[substr($name, 0, $slash)] = true;

            if (count($folders) > 1) {
                return null;
            }
        }

        $prefix = array_key_first($folders);

        return $prefix !== null && $zip->locateName($prefix.'/'.$manifest) !== false ? $prefix.'/' : null;
    }

    /** macOS/Windows files that zipping a folder adds; never package content. */
    private static function isOsClutter(string $name): bool
    {
        return str_starts_with($name, '__MACOSX/')
            || in_array(basename(rtrim($name, '/')), ['.DS_Store', 'Thumbs.db', 'desktop.ini'], true);
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
