<?php

namespace App\Support;

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
            return "modules/{$m[1]}/{$m[2]}/resources/js/pages/{$m[3]}.tsx";
        }

        return "resources/js/pages/{$component}.tsx";
    }
}
