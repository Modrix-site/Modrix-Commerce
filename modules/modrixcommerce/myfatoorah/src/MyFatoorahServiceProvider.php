<?php

namespace EcoModular\MyFatoorah;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers MyFatoorah as a payment gateway in Modrix Commerce.
 */
class MyFatoorahServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/myfatoorah';

    public function register(): void
    {
        $this->app->singleton(MyFatoorahSettings::class);
        $this->app->singleton(MyFatoorahSignature::class);
        $this->app->singleton(MyFatoorahApi::class);
        $this->app->singleton(MyFatoorahGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(MyFatoorahGateway::class));
    }
}
