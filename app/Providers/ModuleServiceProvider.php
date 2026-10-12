<?php

namespace App\Providers;

use App\Core\Extensions\DashboardWidgets;
use App\Core\Extensions\ModuleLoader;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Contracts\SearchProvider;
use App\Domains\Catalog\DatabaseSearchProvider;
use App\Domains\Checkout\CheckoutValidators;
use App\Http\Middleware\EnsureModuleRouteIsAuthorized;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Loads enabled modules. Registered after the core and commerce providers
 * so modules can extend their registries (payment methods, pricing steps,
 * navigation, permissions).
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRepository::class);
        $this->app->singleton(ModuleLoader::class);
        $this->app->singleton(ModuleSettingsSchema::class);

        // Extension points modules register into from their providers.
        $this->app->singleton(StorefrontSlots::class);
        $this->app->singleton(DashboardWidgets::class);
        $this->app->singleton(CheckoutValidators::class);
        $this->app->bindIf(SearchProvider::class, DatabaseSearchProvider::class);

        $this->app->make(Router::class)->aliasMiddleware('module.authorized', EnsureModuleRouteIsAuthorized::class);

        $this->app->make(ModuleLoader::class)->bootEnabled();
    }
}
