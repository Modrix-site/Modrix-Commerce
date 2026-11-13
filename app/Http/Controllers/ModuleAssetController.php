<?php

namespace App\Http\Controllers;

use App\Core\Extensions\ManifestValidator;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a module's prebuilt screens (its `dist/` folder, made by
 * `ecomodular:module:build`), so modules installed from Modrix Cloud or a
 * ZIP have working screens without rebuilding the store's assets.
 *
 * Only enabled modules, only files inside `dist/` (no traversal or symlink
 * escapes), only JavaScript, CSS and the module-ui.json index, with `nosniff`.
 * Hashed files are cached for a year; the index is revalidated so an update
 * takes effect at once.
 */
class ModuleAssetController extends Controller
{
    public const DIST = 'dist';

    public const INDEX = 'module-ui.json';

    private const TYPES = [
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'json' => 'application/json',
    ];

    public function __invoke(string $vendor, string $module, string $path, ModuleRepository $modules): BinaryFileResponse
    {
        $record = $modules->find("{$vendor}/{$module}");
        abort_unless($record !== null && $record->state === ModuleState::Enabled, 404);

        $packageDir = ModuleRepository::packageDir("{$vendor}/{$module}");
        $dist = ManifestValidator::resolvePath($packageDir, self::DIST, directory: true);
        $file = ManifestValidator::resolvePath($packageDir, self::DIST.'/'.$path, directory: false);
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
