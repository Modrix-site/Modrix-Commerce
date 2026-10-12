<?php

namespace App\Providers;

use App\Domains\Cart\Listeners\MergeGuestCartOnLogin;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Domains\Customers\Listeners\CreateCustomerProfile;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the commerce domains: pricing pipeline, listeners and registries.
 */
class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CartPricer::class, function () {
            return new CartPricer;
        });
    }

    public function boot(): void
    {
        Event::listen(Registered::class, CreateCustomerProfile::class);
        Event::listen(Login::class, MergeGuestCartOnLogin::class);
    }
}
