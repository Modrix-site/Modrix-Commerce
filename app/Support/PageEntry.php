<?php

namespace App\Support;

use Illuminate\Support\Facades\Vite;

/**
 * The Vite entry file of an Inertia page, so the root view can preload it.
 * Core pages live in resources/js/pages; module pages ("module/<vendor>/<name>/<page>")
 * live in the module package (see resources/js/lib/modules.ts).
 */
final class PageEntry
{
    public static function for(string $component): string
    {
        if (preg_match('#^module/([a-z0-9-]+)/([a-z0-9-]+)/([A-Za-z0-9/_-]+)$#', $component, $m)) {
            $entry = "modules/{$m[1]}/{$m[2]}/resources/js/pages/{$m[3]}.tsx";
        } else {
            $entry = "resources/js/pages/{$component}.tsx";
        }

        // A section may import a core page directly. Vite then bundles that
        // page into the app instead of emitting a separate manifest entry.
        if (! Vite::isRunningHot()) {
            $path = public_path('build/manifest.json');
            $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
            if (! isset($manifest[$entry])) {
                return 'resources/js/app.tsx';
            }
        }

        return $entry;
    }
}
