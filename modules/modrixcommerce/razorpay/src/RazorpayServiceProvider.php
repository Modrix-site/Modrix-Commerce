<?php

namespace ModrixCommerce\Razorpay;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;

class RazorpayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RazorpaySettings::class);
        $this->app->singleton(RazorpayApi::class);
        $this->app->singleton(RazorpayGateway::class);
    }

    public function boot(PaymentMethods $methods, ExceptionHandler $exceptions): void
    {
        if ($exceptions instanceof Handler) {
            $exceptions->dontFlash(['key_secret', 'test_key_secret', 'webhook_secret', 'test_webhook_secret', 'razorpay_signature']);
        }
        $methods->register($this->app->make(RazorpayGateway::class));
    }
}
