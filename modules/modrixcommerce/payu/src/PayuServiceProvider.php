<?php

namespace EcoModular\PayU;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers PayU as a payment gateway in Modrix Commerce.
 */
class PayuServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/payu';

    public function register(): void
    {
        $this->app->singleton(PayuSettings::class);
        $this->app->singleton(PayuSignature::class);
        $this->app->singleton(PayuApi::class);
        $this->app->singleton(PayuGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(PayuGateway::class));
    }
}
