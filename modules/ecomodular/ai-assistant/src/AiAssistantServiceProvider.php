<?php

namespace EcoModular\AiAssistant;

use App\Core\Localization\Locales;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AiAssistantServiceProvider extends ServiceProvider
{
    public const MODULE = 'ecomodular/ai-assistant';

    public function boot(): void
    {
        Inertia::share('aiAssistant', function () {
            $user = request()->user();
            if (! request()->is('admin', 'admin/*') || ! $user instanceof User || ! $user->can('products.update')) {
                return null;
            }
            $settings = app(AiSettings::class);
            $locales = app(Locales::class);

            return [
                'enabled' => $settings->enabled(),
                'url' => route('admin.ecomodular.ai-assistant.draft'),
                'settingsUrl' => $user->can('settings.manage') ? route('admin.ecomodular.ai-assistant.index') : null,
                'locale' => $locales->default(),
                'tone' => $settings->tone(),
                'locales' => array_map(fn (string $code) => ['code' => $code, 'name' => Locales::name($code)], $locales->enabled()),
            ];
        });
    }
}
