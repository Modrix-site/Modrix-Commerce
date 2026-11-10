<?php

namespace EcoModular\Ziina;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Ziina as a payment gateway in Modrix Commerce.
 */
class ZiinaServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/ziina';

    public function register(): void
    {
        $this->app->singleton(ZiinaSettings::class);
        $this->app->singleton(ZiinaApi::class);
        $this->app->singleton(ZiinaGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(ZiinaGateway::class));
    }
}
