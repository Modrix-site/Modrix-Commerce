<?php

namespace App\Core\Extensions;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Wraps a module's service provider so that an exception in its register()
 * or boot() quarantines the module instead of taking the store down
 * (ADR-029). Fatal engine errors (e.g. memory exhaustion) cannot be caught;
 * safe mode covers those.
 */
final class GuardedModuleProvider extends ServiceProvider
{
    private bool $failed = false;

    /**
     * @param  Closure(Throwable): void  $onFailure
     */
    public function __construct(
        Application $app,
        private readonly ServiceProvider $inner,
        private readonly Closure $onFailure,
    ) {
        parent::__construct($app);
    }

    public function register(): void
    {
        $this->guard(function () {
            $this->inner->register();

            // Mirrors Application::register() for the declarative properties.
            foreach (property_exists($this->inner, 'bindings') ? (array) $this->inner->bindings : [] as $key => $value) {
                $this->app->bind($key, $value);
            }

            foreach (property_exists($this->inner, 'singletons') ? (array) $this->inner->singletons : [] as $key => $value) {
                $this->app->singleton(is_int($key) ? $value : $key, $value);
            }
        });
    }

    public function boot(): void
    {
        $this->guard(function () {
            $this->inner->callBootingCallbacks();

            if (method_exists($this->inner, 'boot')) {
                $this->app->call([$this->inner, 'boot']);
            }

            $this->inner->callBootedCallbacks();
        });
    }

    public function failed(): bool
    {
        return $this->failed;
    }

    private function guard(Closure $callback): void
    {
        if ($this->failed) {
            return;
        }

        try {
            $callback();
        } catch (Throwable $e) {
            $this->failed = true;
            ($this->onFailure)($e);
        }
    }
}
