<?php

namespace ModrixCommerce\CCAvenue;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;

class CCAvenueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CCAvenueSettings::class);
        $this->app->singleton(CCAvenueApi::class);
        $this->app->singleton(CCAvenueGateway::class);
    }

    public function boot(PaymentMethods $methods, ExceptionHandler $exceptions): void
    {
        // Ship credential protection with the package, including on existing core installs.
        if ($exceptions instanceof Handler) {
            $exceptions->dontFlash(['access_code', 'working_key', 'encResp']);
        }
        $methods->register($this->app->make(CCAvenueGateway::class));
    }
}
