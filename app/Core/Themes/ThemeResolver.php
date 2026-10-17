<?php

namespace App\Core\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Chooses the theme for a storefront request:
 *
 *  1. a theme a staff member with `themes.manage` is previewing (their session only);
 *  2. the active theme's applied snapshot;
 *  3. the bundled default theme from disk;
 *  4. the built-in design, so the storefront always renders.
 */
final class ThemeResolver
{
    public const PREVIEW_SESSION_KEY = 'theme_preview';

    public function __construct(
        private readonly ThemeRepository $repository,
        private readonly ThemeCustomizations $customizations,
    ) {}

    public function forRequest(Request $request): ResolvedTheme
    {
        // Memoised on the request (not on this service) so nothing leaks between requests.
        $theme = $request->attributes->get('ecomodular.theme');

        if (! $theme instanceof ResolvedTheme) {
            $theme = $this->resolve($request);
            $request->attributes->set('ecomodular.theme', $theme);
        }

        return $theme;
    }

    public function previewing(Request $request): ?string
    {
        $user = $request->user();
        $name = $request->hasSession() ? $request->session()->get(self::PREVIEW_SESSION_KEY) : null;

        return is_string($name) && $user instanceof User && $user->can('themes.manage') ? $name : null;
    }

    private function resolve(Request $request): ResolvedTheme
    {
        try {
            $preview = $this->previewing($request);

            if ($preview !== null) {
                $theme = $this->repository->find($preview);
                $manifest = $theme?->manifest !== null && $theme->state !== ModuleState::Invalid ? ThemeManifest::fromArray($theme->manifest) : null;

                if ($manifest !== null) {
                    return $this->make($manifest, ResolvedTheme::SOURCE_PREVIEW);
                }
            }

            $active = $this->repository->active();

            if ($active instanceof Extension && $active->applied_manifest !== null) {
                return $this->make(ThemeManifest::fromArray($active->applied_manifest), ResolvedTheme::SOURCE_ACTIVE);
            }

            $default = $this->defaultTheme();

            if ($default !== null) {
                return $this->make($default, ResolvedTheme::SOURCE_DEFAULT);
            }
        } catch (Throwable $e) {
            report($e); // A theme problem must never take the storefront down.
        }

        return $this->builtin();
    }

    private function defaultTheme(): ?ThemeManifest
    {
        $name = (string) config('ecomodular.themes.default', 'ecomodular/essence');
        $dir = ThemeRepository::packageDir($name);

        $file = $dir.DIRECTORY_SEPARATOR.ThemeManifest::FILE;

        if (! is_file($file)) {
            return null;
        }

        // Validation scans the package; only repeat it when the manifest changes.
        $data = Cache::remember('ecomodular.theme.default.'.md5($name.'|'.filemtime($file)), 600, function () use ($name, $dir) {
            [$manifest] = $this->repository->validate($name, $dir);

            return $manifest?->toArray() ?? false;
        });

        return is_array($data) ? ThemeManifest::fromArray($data) : null;
    }

    private function make(ThemeManifest $manifest, string $source): ResolvedTheme
    {
        [$design] = $this->customizations->effectiveDesign($manifest);

        return new ResolvedTheme($manifest, $source, $design, $this->customizations, hasPackage: true);
    }

    private function builtin(): ResolvedTheme
    {
        $manifest = ThemeManifest::fromArray([
            'name' => 'ecomodular/builtin',
            'display_name' => 'Built-in',
            'version' => '0.0.0',
            'platform_version' => '*',
        ]);

        // Page settings belong to the store, so they apply here too (checkout rules).
        $design = array_replace(ThemeSchema::builtinDesign(), PageSettingFields::storeValues());

        return new ResolvedTheme($manifest, ResolvedTheme::SOURCE_BUILTIN, $design, $this->customizations, hasPackage: false);
    }
}
