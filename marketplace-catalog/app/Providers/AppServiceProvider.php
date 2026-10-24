<?php

namespace Marketplace\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Marketplace\Services\SigningKey;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Read from the database once per request.
        $this->app->scoped(SigningKey::class);
    }

    public function boot(): void
    {
        // Indexed strings fit MySQL 5.7 / MariaDB 10.3 utf8mb4 index limits.
        Schema::defaultStringLength(191);
    }
}
