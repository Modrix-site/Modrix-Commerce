<?php

namespace App\Core\Themes;

use App\Core\Extensions\ManifestValidator;

/** Package-owned browser entry points. No server code is loaded from themes. */
final class ThemeFrontend
{
    public const ENTRIES = [
        'index' => 'src/index.tsx',
        'catalog' => 'src/catalog.tsx',
        'cart' => 'src/cart.tsx',
        'header' => 'src/header.tsx',
        'footer' => 'src/footer.tsx',
        'product' => 'src/product.tsx',
        'layout' => 'src/layout.tsx',
        'customizer' => 'src/customizer.tsx',
        'stylesheet' => 'src/style.css',
    ];

    /** @return list<string> */
    public static function errors(string $directory, mixed $frontend): array
    {
        if (! is_array($frontend) || array_diff(array_keys($frontend), [...array_keys(self::ENTRIES), 'pages']) !== []) {
            return ['frontend must contain only supported browser entries and pages.'];
        }

        $errors = [];
        foreach (self::ENTRIES as $entry => $expected) {
            if (array_key_exists($entry, $frontend) && ($frontend[$entry] !== $expected || ManifestValidator::resolvePath($directory, $expected, directory: false) === null)) {
                $errors[] = "frontend.{$entry} must point to the existing {$expected}.";
            }
        }

        if (array_key_exists('pages', $frontend)) {
            $pages = $frontend['pages'];
            if (! is_array($pages) || ($pages !== [] && array_is_list($pages))) {
                return [...$errors, 'frontend.pages must be an object mapping storefront page names to src/pages/*.tsx files.'];
            }

            foreach ($pages as $page => $path) {
                if (! preg_match('#^storefront/[a-z0-9-]+(?:/[a-z0-9-]+)*$#D', (string) $page)
                    || ! is_string($path)
                    || ! preg_match('#^src/pages/[a-z0-9-]+(?:/[a-z0-9-]+)*\.tsx$#D', $path)
                    || ManifestValidator::resolvePath($directory, $path, directory: false) === null) {
                    $errors[] = "frontend.pages.{$page} must map a storefront page to an existing TSX file under src/pages/.";
                }
            }
        }

        return $errors;
    }
}
