<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marketplace server routes exist only on the platform owner's marketplace
 * instance (ECOMODULAR_MARKETPLACE_SERVER=true); everywhere else they 404.
 */
class EnsureMarketplaceServer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('ecomodular.marketplace.server.enabled'), 404);

        return $next($request);
    }
}
