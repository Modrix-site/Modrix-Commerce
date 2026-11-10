<?php

namespace EcoModular\CloudConnector;

use EcoModular\CloudConnector\Console\CloudCheckCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Modrix Cloud on this store. Routes and navigation come from
 * module.json; the provider binds the client and schedules the daily
 * license check (it never disables anything).
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
            $this->commands([CloudCheckCommand::class]);

            $this->app->booted(function () {
                $this->app->make(Schedule::class)->command('modrix:cloud:check')->daily()->withoutOverlapping();
            });
        }
    }
}
