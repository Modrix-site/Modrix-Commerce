<?php

namespace App\Core\Extensions;

use App\Core\Audit\AuditLogger;
use App\Core\Marketplace\PackageArchive;
use App\Core\Marketplace\PackageException;
use App\Core\Themes\ThemeRepository;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use ZipArchive;

/** Receives developer packages without installing, activating or executing them. */
final class UploadedPackage
{
    public function receive(UploadedFile $file, string $type, User $actor): string
    {
        if (! in_array($type, ['theme', 'module'], true)) {
            throw new PackageException('Unsupported package type.');
        }
        $zip = new ZipArchive;
        if ($zip->open($file->getPathname(), ZipArchive::RDONLY) !== true) {
            throw new PackageException('Choose a readable ZIP package.');
        }
        try {
            $entry = $type === 'theme' ? 'theme.json' : 'module.json';
            // At the root, or inside the one folder that was zipped ("prism/theme.json").
            $prefix = PackageArchive::manifestPrefix($zip, $entry);
            $stat = $prefix === null ? false : $zip->statName($prefix.$entry);
            if ($stat === false || $stat['size'] > 524288) {
                throw new PackageException("The ZIP must contain {$entry} at its root or inside a single top folder (maximum 512 KB).");
            }
            $data = json_decode((string) $zip->getFromName($prefix.$entry, 524289), true, 32);
            $name = is_array($data) ? ($data['name'] ?? null) : null;
            if (! is_string($name) || ! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,48}[a-z0-9])?\/[a-z0-9](?:[a-z0-9-]{0,48}[a-z0-9])?$/', $name)) {
                throw new PackageException('The manifest needs a valid vendor/package name.');
            }
        } finally {
            $zip->close();
        }

        return Cache::lock('ecomodular:package-upload', 300)->block(10, function () use ($file, $type, $actor, $name): string {
            $package = PackageArchive::extract($file->getPathname(), $type, $name);
            $dir = $package['dir'];
            try {
                $root = (string) config('ecomodular.'.($type === 'theme' ? 'themes' : 'modules').'.path');
                File::ensureDirectoryExists($root);
                $root = realpath($root);
                if ($root === false) {
                    throw new PackageException('The package directory is unavailable.');
                }
                [$vendor] = explode('/', $name);
                $target = $root.'/'.$name;
                if (is_link($root.'/'.$vendor) || file_exists($target) || is_link($target)) {
                    throw new PackageException('This package already exists or its destination is unavailable. Existing packages are never overwritten by uploads.');
                }
                File::ensureDirectoryExists(dirname($target));
                $moved = false;
                for ($attempt = 0; $attempt < 3; $attempt++) {
                    if (File::moveDirectory($dir, $target)) {
                        $moved = true;
                        break;
                    }
                    // Windows scanners may briefly hold newly validated image files.
                    usleep(100000);
                }
                if (! $moved) {
                    // Some Windows filesystems cannot rename a tree after its files
                    // have been inspected. Copy only into the verified new target.
                    clearstatcache(true, $target);
                    if (@lstat($target) !== false) {
                        throw new PackageException('The package destination already exists.');
                    }
                    if (! File::copyDirectory($dir, $target)) {
                        if (is_dir($target)) {
                            File::deleteDirectory($target);
                        }
                        throw new PackageException('The package could not be saved. Try uploading again.');
                    }
                }
                if ($type === 'theme') {
                    app(ThemeRepository::class)->sync($actor);
                } else {
                    app(ModuleRepository::class)->sync($actor);
                }
                app(AuditLogger::class)->record('package.uploaded', null, ['name' => $name, 'type' => $type, 'version' => $package['manifest']->version], $actor);

                return $name;
            } finally {
                File::deleteDirectory($dir);
            }
        });
    }
}
