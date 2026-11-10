<?php

namespace EcoModular\CurrencyConverter;

use App\Core\Settings\DisplayRateSources;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Publishes live exchange rates into the storefront currency list.
 */
class CurrencyConverterServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/currency-converter';

    public function register(): void
    {
        $this->app->singleton(LiveRates::class);
    }

    public function boot(DisplayRateSources $sources): void
    {
        $sources->add(fn () => $this->app->make(LiveRates::class)->forDisplay());

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->call(fn () => $this->app->make(LiveRates::class)->refresh())
                ->hourly()
                ->name('currency-converter')
                ->withoutOverlapping();
        });
    }
}
