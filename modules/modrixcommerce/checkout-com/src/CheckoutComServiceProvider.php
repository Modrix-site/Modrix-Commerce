<?php

namespace EcoModular\CheckoutCom;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Checkout.com as a payment gateway. Hosted page and Flow share
 * the same payment, return and webhook flow; the settings choose the display.
 */
class CheckoutComServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/checkout-com';

    public function register(): void
    {
        $this->app->singleton(CheckoutComSettings::class);
        $this->app->singleton(CheckoutComApi::class);
        $this->app->singleton(CheckoutComPayload::class);
        $this->app->singleton(CheckoutComGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(CheckoutComGateway::class));
    }
}
