<?php

namespace EcoModular\AuthorizeNet;

use App\Domains\Payments\PaymentMethods;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Authorize.Net Accept Hosted. Customers pay on Authorize.Net;
 * capture confirmation and refunds run on the server.
 */
class AuthorizeNetServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/authorize-net';

    public function register(): void
    {
        $this->app->singleton(AuthorizeNetSettings::class);
        $this->app->singleton(AuthorizeNetApi::class);
        $this->app->singleton(AuthorizeNetPayload::class);
        $this->app->singleton(AuthorizeNetGateway::class);
    }

    public function boot(PaymentMethods $methods): void
    {
        $methods->register($this->app->make(AuthorizeNetGateway::class));
    }
}
