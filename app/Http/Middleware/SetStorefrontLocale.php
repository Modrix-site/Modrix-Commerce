<?php

namespace App\Http\Middleware;

use App\Core\Localization\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the shopper's language for customer-facing pages (storefront,
 * sign-in, account): `?lang=xx` (remembered in a cookie), then the cookie,
 * then the browser's Accept-Language, then the store default. Only enabled
 * languages count. Admin and installer pages keep the application locale.
 */
class SetStorefrontLocale
{
    public const COOKIE = 'storefront_locale';

    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*', 'install', 'install/*')) {
            app()->setLocale($this->resolve($request));
        }

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        try {
            $enabled = $this->locales->enabled();
            $default = $this->locales->default();
        } catch (\Throwable) {
            // Before installation (no settings table) the application locale stands.
            return app()->getLocale();
        }

        $requested = $request->query('lang');
        if (is_string($requested) && in_array($requested, $enabled, true)) {
            Cookie::queue(self::COOKIE, $requested, 60 * 24 * 365, null, null, null, true, false, 'lax');

            return $requested;
        }

        $cookie = $request->cookie(self::COOKIE);
        if (is_string($cookie) && in_array($cookie, $enabled, true)) {
            return $cookie;
        }

        if (count($enabled) > 1) {
            $preferred = $request->getPreferredLanguage($enabled);
            if (is_string($preferred) && in_array($preferred, $enabled, true)) {
                return $preferred;
            }
        }

        return $default;
    }
}
