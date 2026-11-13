<?php

namespace EcoModular\CloudConnector;

use EcoModular\CloudConnector\Console\CloudCheckCommand;
use EcoModular\CloudConnector\Console\CloudRestoreCommand;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Modrix Cloud on this store. Routes and navigation come from
 * module.json; the provider binds the client, schedules the daily license
 * check (it never disables anything) and puts back packages a redeploy
 * removed.
 */
class CloudConnectorServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/cloud-connector';

    public function register(): void
    {
        $this->app->singleton(CloudClient::class);
        $this->app->singleton(StoreLicenses::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CloudCheckCommand::class, CloudRestoreCommand::class]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('modrix:cloud:check')->daily()->withoutOverlapping();
                // Brings back packages a redeploy removed, if the deploy step below could not.
                $schedule->command('modrix:cloud:restore')->everyFiveMinutes()->withoutOverlapping();
            });

            // A deploy runs `migrate`: restore missing packages right after it.
            Event::listen(CommandFinished::class, function (CommandFinished $event) {
                if ($event->command !== 'migrate' || $event->exitCode !== 0 || $this->app->runningUnitTests()) {
                    return;
                }

                try {
                    $this->app->make(PackageRestorer::class)->restore();
                } catch (Throwable $e) {
                    report($e); // Never fail the deploy; the scheduler retries.
                }
            });
        }
    }
}
