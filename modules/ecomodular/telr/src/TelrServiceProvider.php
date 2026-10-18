<?php

namespace EcoModular\Telr;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Telr as a payment gateway. Checkout display (hosted page or
 * iframe) is chosen in the module settings; both use the same session,
 * return and webhook flow.
 */
class TelrServiceProvider extends ServiceProvider
{
    public const MODULE = 'ecomodular/telr';

    public function register(): void
    {
        $this->app->singleton(TelrSettings::class);
        $this->app->singleton(TelrGateway::class);
        $this->app->singleton(TelrApi::class);
        $this->app->singleton(TelrPayload::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(TelrGateway::class));
    }
}
