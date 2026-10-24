<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Until the cut-over, anything without a Laravel route is still
            // served by the legacy front controller (no session middleware,
            // so that controller owns the response).
            Route::fallback(function () {
                // The legacy controller ends the process, which would end a test run.
                abort_if(app()->runningUnitTests(), 404);
                require base_path('src/front.php');
                exit;
            });
        },
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel's default web (sessions, CSRF) and api groups.
        // Guests go to the sign-in page and come back to where they were.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? '/admin/login'
            : '/account/login?next='.rawurlencode($request->getRequestUri()));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin/*') ? '/admin' : '/account');
        // Stripe signs its webhook instead of sending a CSRF token.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password', 'password', 'password_confirmation', 'token',
        ]);
    })
    ->booting(function () {
        // `php artisan serve` replaces the process environment and, on Windows,
        // omits TEMP/TMP. PHP then cannot create the upload temporary file
        // ("unable to create a temporary file") and every thumbnail is rejected.
        ServeCommand::$passthroughVariables = array_values(array_unique([
            ...ServeCommand::$passthroughVariables,
            'HOME',
            'TEMP',
            'TMP',
            'TMPDIR',
            'USERPROFILE',
        ]));
    })->create();
