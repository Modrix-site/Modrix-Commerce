<?php

namespace App\Providers;

use App\Core\Settings\GeneralSettings;
use App\Core\Settings\MailSettings;
use App\Core\Settings\SettingsRepository;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Applies Settings → General at runtime: SMTP mail (on boot and before
 * every queued job) and the session lifetime.
 */
class GeneralSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailSettings::class);
    }

    public function boot(): void
    {
        config(['session.lifetime_default' => config('session.lifetime')]);

        try {
            $lifetime = $this->app->make(GeneralSettings::class)->security()['session_lifetime'];
            if ($lifetime !== null) {
                config(['session.lifetime' => $lifetime]);
            }
            $this->app->make(MailSettings::class)->apply();
        } catch (Throwable) {
            // Not installed yet (no settings table): keep the .env configuration.
        }

        Queue::before(function () {
            $this->app->make(SettingsRepository::class)->reload();
            $this->app->make(MailSettings::class)->apply();
        });
    }
}
