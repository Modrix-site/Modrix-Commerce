<?php

namespace EcoModular\Cashfree;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Cashfree Payments as a payment gateway in Modrix Commerce.
 */
class CashfreeServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/cashfree';

    public function register(): void
    {
        $this->app->singleton(CashfreeSettings::class);
        $this->app->singleton(CashfreeSignature::class);
        $this->app->singleton(CashfreeApi::class);
        $this->app->singleton(CashfreeGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(CashfreeGateway::class));
    }
}
