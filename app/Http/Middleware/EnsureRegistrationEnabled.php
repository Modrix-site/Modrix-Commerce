<?php

namespace App\Http\Middleware;

use App\Core\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings → General → Security can turn customer sign-up off.
 */
class EnsureRegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register', 'register.store')) {
            return $next($request);
        }

        $enabled = rescue(fn () => app(GeneralSettings::class)->registrationEnabled(), true, false);

        abort_unless($enabled, 404);

        return $next($request);
    }
}
