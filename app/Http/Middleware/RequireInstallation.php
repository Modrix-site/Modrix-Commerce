<?php

namespace App\Http\Middleware;

use App\Core\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends visitors to the installer until the platform has been installed.
 */
class RequireInstallation
{
    public function __construct(private readonly InstallationState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->state->isInstalled()) {
            return redirect()->route('install.show');
        }

        return $next($request);
    }
}
