<?php

namespace EcoModular\Tap;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Tap as a payment gateway. Checkout themes already list every
 * configured gateway, so the Tap method appears without a theme-specific page.
 */
class TapServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/tap';

    public function register(): void
    {
        $this->app->singleton(TapSettings::class);
        $this->app->singleton(TapApi::class);
        $this->app->singleton(TapPayload::class);
        $this->app->singleton(TapGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(TapGateway::class));
    }
}
