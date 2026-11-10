<?php

namespace ModrixCommerce\PayTabs;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;

class PayTabsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PayTabsSettings::class);
        $this->app->singleton(PayTabsApi::class);
        $this->app->singleton(PayTabsGateway::class);
    }

    public function boot(PaymentMethods $methods, ExceptionHandler $exceptions): void
    {
        if ($exceptions instanceof Handler) {
            $exceptions->dontFlash(['server_key', 'test_server_key', 'signature', 'token']);
        }
        $methods->register($this->app->make(PayTabsGateway::class));
    }
}
