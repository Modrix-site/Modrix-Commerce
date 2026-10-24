<?php

namespace Marketplace\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Indexed strings fit MySQL 5.7 / MariaDB 10.3 utf8mb4 index limits.
        Schema::defaultStringLength(191);
    }
}
