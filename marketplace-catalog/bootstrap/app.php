<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // The catalog UI and license API live in the front controller.
            // This route has no session middleware so that controller owns the response.
            Route::any('{path?}', function () {
                require base_path('src/front.php');
                exit;
            })->where('path', '.*');
        },
    )
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
