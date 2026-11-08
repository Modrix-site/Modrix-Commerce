<?php

namespace App\Providers;

use App\Core\Catalog\ProductLabels;
use App\Core\Extensions\DashboardWidgets;
use App\Core\Extensions\ModuleContentSecurity;
use App\Core\Extensions\ModuleLoader;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Seo\Sitemap;
use App\Domains\Catalog\Contracts\SearchProvider;
use App\Domains\Catalog\DatabaseSearchProvider;
use App\Domains\Checkout\CheckoutValidators;
use App\Http\Middleware\EnsureModuleRouteIsAuthorized;
use Illuminate\Database\Eloquent\Model;
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
        // Scoped: it holds the scoped SettingsRepository, which queue workers reload per job.
        $this->app->scoped(ModuleSettingsSchema::class);

        // Extension points modules register into from their providers.
        $this->app->singleton(StorefrontSlots::class);
        $this->app->singleton(ModuleContentSecurity::class);
        $this->app->singleton(DashboardWidgets::class);
        $this->app->singleton(CheckoutValidators::class);
        $this->app->singleton(Sitemap::class);
        $this->app->singleton(ProductLabels::class);
        $this->app->bindIf(SearchProvider::class, DatabaseSearchProvider::class);

        $this->app->make(Router::class)->aliasMiddleware('module.authorized', EnsureModuleRouteIsAuthorized::class);

        // Not during register(): deferred services such as the cache are only known once
        // every provider has registered. "booting" runs after that and before any provider
        // boots, so module providers still register before the platform boots.
        $this->app->booting(function () {
            // Eloquent is wired in DatabaseServiceProvider::boot(), which has not run yet;
            // the module registry and quarantine use models. Same calls, idempotent.
            Model::setConnectionResolver($this->app->make('db'));
            Model::setEventDispatcher($this->app->make('events'));

            $this->app->make(ModuleLoader::class)->bootEnabled();
        });
    }
}
