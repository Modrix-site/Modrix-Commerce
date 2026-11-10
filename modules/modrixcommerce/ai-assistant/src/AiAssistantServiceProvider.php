<?php

namespace EcoModular\AiAssistant;

use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use App\Core\Localization\Locales;
use App\Core\Localization\TranslationDrafts;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AiAssistantServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/ai-assistant';

    public function boot(): void
    {
        app(TranslationDrafts::class)->register(
            fn (string $source, string $sourceLocale, string $targetLocale): string => app(AiClient::class)->draft(
                'translate', 'storefront', Locales::name($targetLocale),
                ['text' => $source], ['text' => 10000], 'Source language: '.Locales::name($sourceLocale), app(AiSettings::class)->tone(),
            )['text'],
            fn (): bool => app(ModuleRepository::class)->find(self::MODULE)?->state === ModuleState::Enabled && app(AiSettings::class)->enabled(),
        );
        if ($this->app->bound('request')) {
            try {
                Inertia::share('aiAssistant', function () {
                    if (! request()->is('admin', 'admin/*')) {
                        return null;
                    }
                    $user = request()->user();
                    if (! $user instanceof User || ! $user->can('products.update')) {
                        return null;
                    }
                    $draft = 'admin.modrixcommerce.ai-assistant.draft';
                    $settingsRoute = 'admin.modrixcommerce.ai-assistant.index';
                    if (! Route::has($draft)) {
                        return null;
                    }
                    $settings = app(AiSettings::class);
                    $locales = app(Locales::class);

                    return [
                        'enabled' => $settings->enabled(),
                        'url' => route($draft),
                        'settingsUrl' => $user->can('settings.manage') && Route::has($settingsRoute) ? route($settingsRoute) : null,
                        'locale' => $locales->default(),
                        'tone' => $settings->tone(),
                        'locales' => array_map(fn (string $code) => ['code' => $code, 'name' => Locales::name($code)], $locales->enabled()),
                    ];
                });
            } catch (\Throwable) {
                // Request or DevTools not ready; do not fail provider boot.
            }
        }
    }
}
