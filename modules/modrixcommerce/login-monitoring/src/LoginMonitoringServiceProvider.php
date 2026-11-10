<?php

namespace EcoModular\LoginMonitoring;

use EcoModular\LoginMonitoring\Console\CleanExpiredLockoutsCommand;
use EcoModular\LoginMonitoring\Http\Middleware\LoginPrecheckMiddleware;
use EcoModular\LoginMonitoring\Listeners\TrackFailedLogin;
use EcoModular\LoginMonitoring\Listeners\TrackSuccessfulLogin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LoginMonitoringServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/login-monitoring';

    public function register(): void
    {
        //
    }

    public function boot(Router $router): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanExpiredLockoutsCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('login-monitoring:clean-expired')->hourly();
            });
        }

        // Register authentication event listeners
        Event::listen(Login::class, TrackSuccessfulLogin::class);
        Event::listen(Failed::class, TrackFailedLogin::class);

        // Push login lockout precheck middleware to web group
        $router->pushMiddlewareToGroup('web', LoginPrecheckMiddleware::class);
    }
}
