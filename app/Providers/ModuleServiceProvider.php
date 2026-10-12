<?php

namespace App\Providers;

use App\Core\Extensions\ModuleLoader;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
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

        $this->app->make(Router::class)->aliasMiddleware('module.authorized', EnsureModuleRouteIsAuthorized::class);

        $this->app->make(ModuleLoader::class)->bootEnabled();
    }
}
