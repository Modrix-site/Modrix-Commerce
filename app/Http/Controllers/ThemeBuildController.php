<?php

namespace App\Http\Controllers;

use App\Core\Extensions\ManifestValidator;
use App\Core\Extensions\ModuleState;
use App\Core\Themes\ThemeRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a theme's prebuilt screens (its `dist/` folder, made by
 * `ecomodular:module:build`), so themes installed from Modrix Cloud or a ZIP
 * render without rebuilding the store's assets.
 *
 * Any installed, valid theme (the editor previews themes before they are
 * active), only files inside `dist/`, only JavaScript, CSS and the
 * theme-ui.json index, with `nosniff`.
 */
class ThemeBuildController extends Controller
{
    public const INDEX = 'theme-ui.json';

    private const TYPES = [
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'json' => 'application/json',
    ];

    public function __invoke(string $vendor, string $theme, string $path, ThemeRepository $themes): BinaryFileResponse
    {
        $name = "{$vendor}/{$theme}";
        $record = $themes->find($name);
        abort_unless($record !== null && $record->state !== ModuleState::Invalid, 404);

        $packageDir = ThemeRepository::packageDir($name);
        $dist = ManifestValidator::resolvePath($packageDir, 'dist', directory: true);
        $file = ManifestValidator::resolvePath($packageDir, 'dist/'.$path, directory: false);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        abort_unless(
            $dist !== null && $file !== null && str_starts_with($file, $dist.DIRECTORY_SEPARATOR)
                && isset(self::TYPES[$extension])
                && ($extension !== 'json' || $path === self::INDEX),
            404,
        );

        return response()->file($file, [
            'Content-Type' => self::TYPES[$extension],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $path === self::INDEX ? 'no-cache' : 'public, max-age=31536000, immutable',
        ]);
    }
}
