<?php

namespace App\Providers;

use App\Core\Settings\GeneralSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->make(Dispatcher::class)->listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() === 'sqlite') {
                // Nested writes use temporary statement journals. Keep those in
                // memory so Windows service/sandbox temp paths cannot break saves.
                // The main database and rollback journal remain durable on disk.
                $event->connection->getPdo()->exec('PRAGMA temp_store = MEMORY');
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Surface N+1 queries and silently dropped attributes during development and tests.
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Settings → General → Security can set the minimum length.
        Password::defaults(function (): ?Password {
            $minimum = rescue(fn () => $this->app->make(GeneralSettings::class)->security()['password_min'], null, false);

            if (app()->isProduction()) {
                return Password::min($minimum ?? 12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised();
            }

            return $minimum === null ? null : Password::min($minimum);
        });
    }
}
