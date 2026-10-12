<?php

namespace App\Http\Middleware;

use App\Core\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The installer does not exist once installation has completed, nor when
 * the web installer is disabled (ECOMODULAR_WEB_INSTALLER=false: CLI only).
 */
class BlockAfterInstallation
{
    public function __construct(private readonly InstallationState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->state->isInstalled() || ! config('ecomodular.installer.web', true), 404);

        return $next($request);
    }
}
