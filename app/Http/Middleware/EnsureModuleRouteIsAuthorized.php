<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to every module admin route. A module admin route that does not
 * declare a `can:` permission middleware is refused, so a module cannot
 * expose an admin page to every staff member by accident.
 */
class EnsureModuleRouteIsAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $middleware = $route === null ? [] : $route->gatherMiddleware();

        $hasPermission = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'can:'));

        if (! $hasPermission) {
            Log::warning('Module admin route without a permission middleware was refused.', ['route' => $route?->getName()]);
            abort(403, 'This module page does not declare a permission.');
        }

        return $next($request);
    }
}
