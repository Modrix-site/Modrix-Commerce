<?php

namespace EcoModular\Paytm;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Paytm as a payment gateway in Modrix Commerce.
 */
class PaytmServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/paytm';

    public function register(): void
    {
        $this->app->singleton(PaytmSettings::class);
        $this->app->singleton(PaytmApi::class);
        $this->app->singleton(PaytmGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(PaytmGateway::class));
    }
}
