<?php

use App\Http\Middleware\BlockAfterInstallation;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogoutDisabledUsers;
use App\Http\Middleware\RequireInstallation;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/install.php'));
            Route::middleware('installed')
                ->prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));
            Route::middleware(['web', 'installed', 'auth', 'verified', 'staff'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            LogoutDisabledUsers::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
            'installed' => RequireInstallation::class,
            'not-installed' => BlockAfterInstallation::class,
        ]);

        // Before installation there are no accounts, so send visitors to the installer, not the login page.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: RequireInstallation::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Never write credentials or tokens from request input into logs.
        $exceptions->dontFlash([
            'current_password', 'password', 'password_confirmation', 'token', 'setup_token',
        ]);
    })->create();
