<?php

namespace EcoModular\NGenius;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Network International (N-Genius) as a payment gateway in Modrix Commerce.
 */
class NGeniusServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/ngenius';

    public function register(): void
    {
        $this->app->singleton(NGeniusSettings::class);
        $this->app->singleton(NGeniusApi::class);
        $this->app->singleton(NGeniusGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(NGeniusGateway::class));
    }
}
