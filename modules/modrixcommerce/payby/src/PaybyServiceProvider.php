<?php

namespace EcoModular\PayBy;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers PayBy as a payment gateway in Modrix Commerce.
 */
class PaybyServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/payby';

    public function register(): void
    {
        $this->app->singleton(PaybySettings::class);
        $this->app->singleton(PaybySignature::class);
        $this->app->singleton(PaybyApi::class);
        $this->app->singleton(PaybyGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(PaybyGateway::class));
    }
}
