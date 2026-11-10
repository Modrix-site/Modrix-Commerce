<?php

namespace App\Http\Controllers;

use App\Core\Extensions\ManifestValidator;
use App\Core\Extensions\ModuleState;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves files from a theme's assets directory. Only images and WOFF2 fonts,
 * only inside the declared assets directory (no traversal or symlink
 * escapes), with `nosniff` and a CSP that forbids everything, so a file can
 * never be interpreted as a page or script.
 */
class ThemeAssetController extends Controller
{
    private const TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'woff2' => 'font/woff2',
    ];

    public function __invoke(string $vendor, string $theme, string $path, ThemeRepository $themes): BinaryFileResponse
    {
        $name = "{$vendor}/{$theme}";
        $record = $themes->find($name);
        $data = $record !== null && in_array($record->state, [ModuleState::Enabled, ModuleState::Failed], true)
            ? ($record->applied_manifest ?? $record->manifest)
            : $record?->manifest;

        // Before discovery, the bundled default theme is still served.
        if ($record === null && $name === config('ecomodular.themes.default', 'modrixcommerce/essence')) {
            $data = ['assets' => 'assets'];
        }

        abort_if($data === null || ($record !== null && $record->state === ModuleState::Invalid && $record->applied_manifest === null), 404);

        $packageDir = ThemeRepository::packageDir($name);
        $assets = isset($data['assets']) ? ManifestValidator::resolvePath($packageDir, (string) $data['assets'], directory: true) : null;
        $file = ManifestValidator::resolvePath($packageDir, $path, directory: false);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        abort_unless(
            $assets !== null && $file !== null && str_starts_with($file, $assets.DIRECTORY_SEPARATOR)
                && isset(self::TYPES[$extension])
                && basename($file) !== ThemeManifest::FILE,
            404,
        );

        return response()->file($file, [
            'Content-Type' => self::TYPES[$extension],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
