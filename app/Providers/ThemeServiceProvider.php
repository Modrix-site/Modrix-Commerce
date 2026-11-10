<?php

namespace App\Providers;

use App\Core\Localization\ContentTranslations;
use App\Core\Localization\TranslationDrafts;
use App\Core\Themes\StorefrontLinks;
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
        $this->app->singleton(TranslationDrafts::class);
        $this->app->scoped(ContentTranslations::class);
        $this->app->scoped(StorefrontLinks::class);
        $this->app->singleton(ThemeManifestValidator::class);
        $this->app->singleton(ThemeRepository::class);
        $this->app->singleton(ThemeCustomizations::class);
        $this->app->singleton(ThemeResolver::class);
        $this->app->singleton(\App\Core\Themes\Sections\ThemeTemplates::class);
    }
}
