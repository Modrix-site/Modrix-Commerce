<?php

namespace EcoModular\Instamojo;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Instamojo as a payment gateway in Modrix Commerce.
 */
class InstamojoServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/instamojo';

    public function register(): void
    {
        $this->app->singleton(InstamojoSettings::class);
        $this->app->singleton(InstamojoSignature::class);
        $this->app->singleton(InstamojoApi::class);
        $this->app->singleton(InstamojoGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(InstamojoGateway::class));
    }
}
