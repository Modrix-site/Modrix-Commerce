<?php

namespace App\Core\Extensions;

use Composer\Semver\Comparator;

/**
 * Where module and theme packages live.
 *
 * Two places, read together:
 *  - the install root (`ecomodular.<type>.path`, under storage/ by default):
 *    everything installed from Modrix Cloud or a ZIP is written here. It sits
 *    with the uploads and backups, so it survives a redeploy from Git.
 *  - the bundled root (`ecomodular.<type>.bundled_path`, the repository's
 *    modules/ or themes/ folder): packages that ship with the code (the Cloud
 *    Connector, the starter theme) and packages placed there by hand. A
 *    deploy or core update replaces it.
 *
 * When both hold a package, the newer version wins; the install root wins a tie.
 */
final class PackageRoots
{
    public const MODULES = 'modules';

    public const THEMES = 'themes';

    /** @var array<string, string> type|name => directory, for this request */
    private static array $located = [];

    public static function installRoot(string $type): string
    {
        $default = $type === self::THEMES ? base_path('themes') : base_path('modules');

        return rtrim((string) (config("ecomodular.{$type}.path") ?: $default), '/\\');
    }

    public static function bundledRoot(string $type): ?string
    {
        $path = config("ecomodular.{$type}.bundled_path");

        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = rtrim($path, '/\\');

        return self::same($path, self::installRoot($type)) ? null : $path;
    }

    /**
     * @return list<string> install root first
     */
    public static function roots(string $type): array
    {
        return array_values(array_filter([self::installRoot($type), self::bundledRoot($type)]));
    }

    /**
     * The directory holding a package: the copy that wins when both roots have
     * one, otherwise where it would be installed.
     */
    public static function locate(string $type, string $name): string
    {
        $key = $type.'|'.implode('|', self::roots($type)).'|'.$name;

        if (isset(self::$located[$key]) && is_dir(self::$located[$key])) {
            return self::$located[$key];
        }

        $manifest = $type === self::THEMES ? 'theme.json' : 'module.json';
        $relative = str_replace('/', DIRECTORY_SEPARATOR, $name);
        $best = null;
        $bestVersion = null;

        foreach (self::roots($type) as $root) {
            $dir = $root.DIRECTORY_SEPARATOR.$relative;

            if (! is_file($dir.DIRECTORY_SEPARATOR.$manifest)) {
                continue;
            }

            $version = self::version($dir.DIRECTORY_SEPARATOR.$manifest);

            if ($best === null || ($version !== null && ($bestVersion === null || Comparator::greaterThan($version, $bestVersion)))) {
                $best = $dir;
                $bestVersion = $version;
            }
        }

        if ($best === null) {
            return self::installDir($type, $name);
        }

        return self::$located[$key] = $best;
    }

    /** Where an install or update from Modrix Cloud or a ZIP writes the package. */
    public static function installDir(string $type, string $name): string
    {
        return self::installRoot($type).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
    }

    /**
     * Every package found under the roots, the winning copy of each.
     *
     * @return array<string, string> vendor/name => directory, sorted by name
     */
    public static function scan(string $type, string $manifest): array
    {
        self::forget();
        $found = [];

        foreach (self::roots($type) as $root) {
            foreach (glob($root.'/*/*/'.$manifest) ?: [] as $file) {
                $dir = dirname($file);
                $name = basename(dirname($dir)).'/'.basename($dir);
                $found[$name] = true;
            }
        }

        $packages = [];
        foreach (array_keys($found) as $name) {
            $packages[$name] = self::locate($type, $name);
        }

        ksort($packages);

        return $packages;
    }

    /**
     * The root a real package directory sits directly under (root/vendor/name),
     * or null when it is anywhere else.
     */
    public static function rootOf(string $type, string $dir): ?string
    {
        $real = realpath($dir);

        if ($real === false) {
            return null;
        }

        foreach (self::roots($type) as $root) {
            $realRoot = realpath($root);

            if ($realRoot !== false && dirname($real, 2) === $realRoot) {
                return $realRoot;
            }
        }

        return null;
    }

    /** Clears the per-request lookup (after files were added, moved or removed). */
    public static function forget(): void
    {
        self::$located = [];
    }

    private static function version(string $manifestFile): ?string
    {
        if (filesize($manifestFile) > 524288) {
            return null;
        }

        $data = json_decode((string) file_get_contents($manifestFile), true);
        $version = is_array($data) ? ($data['version'] ?? null) : null;

        return is_string($version) && preg_match('/^\d+\.\d+\.\d+/', $version) === 1 ? $version : null;
    }

    private static function same(string $a, string $b): bool
    {
        $ra = realpath($a);
        $rb = realpath($b);

        if ($ra !== false && $rb !== false) {
            return $ra === $rb;
        }

        return str_replace('\\', '/', $a) === str_replace('\\', '/', $b);
    }
}
