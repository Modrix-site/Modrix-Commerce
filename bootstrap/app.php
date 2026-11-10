<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\ThemeAssetController;
use App\Http\Middleware\BlockAfterInstallation;
use App\Http\Middleware\EnsureMarketplaceServer;
use App\Http\Middleware\EnsureRegistrationEnabled;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogoutDisabledUsers;
use App\Http\Middleware\PrepareInstallEnvironment;
use App\Http\Middleware\RequireInstallation;
use App\Http\Middleware\RequireStaffTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetStorefrontLocale;
use App\Http\Middleware\StorefrontMaintenance;
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
            // Static theme files: no session or cookies needed.
            Route::get('theme-assets/{vendor}/{theme}/{path}', ThemeAssetController::class)
                ->where(['vendor' => '[a-z0-9-]+', 'theme' => '[a-z0-9-]+', 'path' => '[A-Za-z0-9_./-]+'])
                ->name('theme.asset');
            // Readiness for monitors: overall status only, details with ECOMODULAR_HEALTH_TOKEN.
            Route::get('health', HealthController::class)->middleware('throttle:60,1')->name('health');
            require base_path('routes/marketplace.php');
            Route::middleware('installed')
                ->prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));
            Route::middleware(['web', 'installed', 'auth', 'verified', 'staff', 'staff.two-factor'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Before installation: .env/APP_KEY and file sessions, ahead of cookies and sessions.
        $middleware->web(prepend: [PrepareInstallEnvironment::class]);

        $middleware->web(append: [
            EnsureRegistrationEnabled::class,
            SetStorefrontLocale::class,
            LogoutDisabledUsers::class,
            HandleAppearance::class,
            StorefrontMaintenance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
            'staff.two-factor' => RequireStaffTwoFactor::class,
            'installed' => RequireInstallation::class,
            'not-installed' => BlockAfterInstallation::class,
            'marketplace.server' => EnsureMarketplaceServer::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('login'));

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
            'current_password', 'password', 'password_confirmation', 'token', 'setup_token', 'db_password', 'database_password',
            'api_key',
        ]);
    })->create();
