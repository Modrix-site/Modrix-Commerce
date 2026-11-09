<?php

namespace App\Http\Middleware;

use App\Core\Installation\DatabaseSetup;
use App\Core\Installation\InstallationState;
use App\Core\Installation\InstallerException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until installation is done, makes a fresh upload work with no database:
 * creates .env and APP_KEY if missing and keeps sessions and cache in files.
 * Runs first in the web group, before cookies and sessions start.
 */
class PrepareInstallEnvironment
{
    /** Request attribute: the configured session driver replaced by file. */
    public const SESSION_DRIVER = 'install.session_driver';

    public function __construct(
        private readonly InstallationState $state,
        private readonly DatabaseSetup $setup,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->isInstalled()) {
            return $next($request);
        }

        try {
            $this->setup->prepareEnvironment();
        } catch (InstallerException $e) {
            return response($e->getMessage(), 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if (config('session.driver') === 'database') {
            // InstallController::store() moves the owner's session back to it.
            $request->attributes->set(self::SESSION_DRIVER, 'database');
            config(['session.driver' => 'file']);
        }

        if (config('cache.default') === 'database') {
            config(['cache.default' => 'file']);
        }

        return $next($request);
    }
}
