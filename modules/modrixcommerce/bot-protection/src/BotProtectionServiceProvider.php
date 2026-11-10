<?php

namespace EcoModular\BotProtection;

use EcoModular\BotProtection\Console\CleanExpiredBlocksCommand;
use EcoModular\BotProtection\Console\GenerateAttackReportCommand;
use EcoModular\BotProtection\Http\Middleware\BotProtectionMiddleware;
use EcoModular\BotProtection\Listeners\MonitorFailedLogin;
use EcoModular\BotProtection\Listeners\MonitorUserRegistration;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BotProtectionServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function register(): void
    {
        //
    }

    public function boot(Router $router): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'bot-protection');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateAttackReportCommand::class,
                CleanExpiredBlocksCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('bot:daily-report')->dailyAt('00:05');
                $schedule->command('bot:clean-expired')->hourly();
            });
        }

        // Register Auth Event Listeners for Credential Stuffing and Fake Registrations
        Event::listen(Failed::class, MonitorFailedLogin::class);
        Event::listen(Registered::class, MonitorUserRegistration::class);

        // Push real-time bot protection middleware to the web router group
        $router->pushMiddlewareToGroup('web', BotProtectionMiddleware::class);
    }
}
