<?php

namespace EcoModular\AmazonPaymentServices;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Amazon Payment Services as a payment gateway in Modrix Commerce.
 */
class AmazonPaymentServicesServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/amazon-payment-services';

    public function register(): void
    {
        $this->app->singleton(AmazonPaymentServicesSettings::class);
        $this->app->singleton(AmazonPaymentServicesSignature::class);
        $this->app->singleton(AmazonPaymentServicesAmount::class);
        $this->app->singleton(AmazonPaymentServicesApi::class);
        $this->app->singleton(AmazonPaymentServicesGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(AmazonPaymentServicesGateway::class));
    }
}
