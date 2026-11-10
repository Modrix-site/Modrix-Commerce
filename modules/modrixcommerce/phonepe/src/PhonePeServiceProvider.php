<?php

namespace EcoModular\PhonePe;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers PhonePe as a payment gateway in Modrix Commerce.
 */
class PhonePeServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/phonepe';

    public function register(): void
    {
        $this->app->singleton(PhonePeSettings::class);
        $this->app->singleton(PhonePeSignature::class);
        $this->app->singleton(PhonePeApi::class);
        $this->app->singleton(PhonePeGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(PhonePeGateway::class));
    }
}
