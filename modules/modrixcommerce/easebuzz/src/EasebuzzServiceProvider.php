<?php

namespace EcoModular\Easebuzz;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Easebuzz as a payment gateway in Modrix Commerce.
 */
class EasebuzzServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/easebuzz';

    public function register(): void
    {
        $this->app->singleton(EasebuzzSettings::class);
        $this->app->singleton(EasebuzzSignature::class);
        $this->app->singleton(EasebuzzApi::class);
        $this->app->singleton(EasebuzzGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(EasebuzzGateway::class));
    }
}
