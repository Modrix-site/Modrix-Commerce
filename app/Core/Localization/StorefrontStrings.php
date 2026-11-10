<?php

namespace App\Core\Localization;

use Illuminate\Support\Facades\File;

/**
 * Finds the storefront texts in the code: string literals passed to `t()`
 * and `msg()` in the customer-facing React files, and to `st()` in PHP. The
 * result is the source catalogue `lang/storefront/en.json`.
 */
final class StorefrontStrings
{
    /** Customer-facing front-end files and folders (relative to the base path). */
    public const SCRIPT_PATHS = [
        'resources/js/pages/storefront/home.tsx',
        'resources/js/pages/storefront/listing.tsx',
        'resources/js/pages/storefront/product.tsx',
        'resources/js/pages/storefront/cart.tsx',
        'resources/js/pages/storefront/checkout.tsx',
        'resources/js/pages/storefront/order-complete.tsx',
        'resources/js/pages/storefront/account',
        'resources/js/pages/storefront/auth',
        'resources/js/pages/auth',
        'resources/js/components/storefront',
        'resources/js/components/admin/status-badge.tsx',
        'resources/js/components/passkey-verify.tsx',
        'resources/js/components/password-input.tsx',
        'resources/js/layouts/storefront-layout.tsx',
        'resources/js/layouts/auth-layout.tsx',
    ];

    /**
     * @return list<string> sorted, unique
     */
    public function extract(): array
    {
        $keys = [];

        foreach ($this->files(self::SCRIPT_PATHS, ['tsx', 'ts']) as $file) {
            preg_match_all('/(?<![\w.])(?:t|msg)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', (string) file_get_contents($file), $m);
            array_push($keys, ...array_map(fn (string $s, string $q) => stripcslashes($s), $m[2], $m[1]));
        }

        foreach ($this->files(['app'], ['php']) as $file) {
            preg_match_all('/(?<![\w>:$])st\(\s*\'((?:\\\\.|[^\'])*)\'/s', (string) file_get_contents($file), $m);
            array_push($keys, ...array_map(fn (string $s) => str_replace(["\\'", '\\\\'], ["'", '\\'], $s), $m[1]));
        }

        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * Writes the source catalogue (English: every key maps to itself).
     *
     * @return int number of texts
     */
    public function writeSource(): int
    {
        $keys = $this->extract();
        File::ensureDirectoryExists(lang_path('storefront'));
        File::put(lang_path('storefront/en.json'), json_encode(array_combine($keys, $keys), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        return count($keys);
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function files(array $paths, array $extensions): array
    {
        $files = [];

        foreach ($paths as $path) {
            $full = base_path($path);

            if (is_file($full)) {
                $files[] = $full;
            } elseif (is_dir($full)) {
                foreach (File::allFiles($full) as $file) {
                    if (in_array($file->getExtension(), $extensions, true)) {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }

        return $files;
    }
}
