<?php

namespace EcoModular\Paypal;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers PayPal Checkout. Customers approve on PayPal; capture and refunds
 * run on the server.
 */
class PaypalServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/paypal';

    public function register(): void
    {
        $this->app->singleton(PaypalSettings::class);
        $this->app->singleton(PaypalApi::class);
        $this->app->singleton(PaypalPayload::class);
        $this->app->singleton(PaypalGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(PaypalGateway::class));
    }
}
