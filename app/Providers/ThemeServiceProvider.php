<?php

namespace App\Providers;

use App\Core\Themes\ThemeCustomizations;
use App\Core\Themes\ThemeManifestValidator;
use App\Core\Themes\ThemeRepository;
use App\Core\Themes\ThemeResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Theme engine services (ADR-031). Themes are data: nothing here loads or
 * runs code from a theme package.
 */
class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeManifestValidator::class);
        $this->app->singleton(ThemeRepository::class);
        $this->app->singleton(ThemeCustomizations::class);
        $this->app->singleton(ThemeResolver::class);
    }
}
