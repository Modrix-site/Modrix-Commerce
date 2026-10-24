<?php

namespace Marketplace\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Marketplace\Services\Installer;
use Marketplace\Services\InstallerException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until setup is done every page leads to /install; afterwards /install is
 * gone. Runs before sessions and cookies, so it can prepare .env first.
 */
class RequireInstallation
{
    public function __construct(private readonly Installer $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $installing = $request->is('install', 'install/*');

        if ($this->installer->isInstalled()) {
            abort_if($installing, 404);

            return $next($request);
        }

        try {
            $this->installer->prepareEnvironment();
        } catch (InstallerException $e) {
            return response($e->getMessage(), 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        // No database yet: keep the installer's session and cache in files.
        foreach (['session.driver', 'cache.default'] as $key) {
            if (config($key) === 'database') {
                config([$key => 'file']);
            }
        }

        if ($installing || $request->is('up')) {
            return $next($request);
        }

        return redirect('/install');
    }
}
