<?php

namespace App\Core\Extensions;

use App\Core\Admin\AdminNavigation;
use App\Core\Authorization\PermissionRegistry;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Throwable;

/**
 * Loads enabled modules into the running application: PSR-4 autoloading,
 * declared permissions and admin navigation, the guarded service provider,
 * and declared routes under fixed prefixes and middleware.
 *
 * Nothing is loaded in safe mode (ECOMODULAR_SAFE_MODE=true).
 */
final class ModuleLoader
{
    /** @var array<string, true> */
    private array $loaded = [];

    /** @var array<string, Throwable> */
    private array $failures = [];

    public function __construct(
        private readonly Application $app,
        private readonly ModuleRepository $repository,
    ) {}

    public static function safeMode(): bool
    {
        return (bool) config('ecomodular.modules.safe_mode', false);
    }

    public function bootEnabled(): void
    {
        if (self::safeMode()) {
            return;
        }

        try {
            $modules = $this->repository->enabledForBoot();
        } catch (Throwable) {
            return; // Not installed or not migrated yet.
        }

        foreach ($modules as $module) {
            if (ModuleRepository::diskVersion($module['path']) !== $module['version']) {
                // Files changed since enabling: never run new code against the old schema.
                $this->repository->sync();

                continue;
            }

            $this->load($module['name'], $module['path'], Manifest::fromArray($module['manifest']));
        }
    }

    /**
     * @return bool false when the module failed while loading (it is then quarantined)
     */
    public function load(string $name, string $path, Manifest $manifest): bool
    {
        if (isset($this->loaded[$name])) {
            return ! isset($this->failures[$name]);
        }

        $this->loaded[$name] = true;
        $dir = ModuleRepository::packageDir($path);

        try {
            $this->autoload($dir, $manifest);
            $this->declarations($manifest);
            $this->provider($name, $manifest);
        } catch (Throwable $e) {
            $this->fail($name, $e);
        }

        if (! isset($this->failures[$name])) {
            $this->app->booted(fn () => $this->routes($name, $dir, $manifest));
        }

        return ! isset($this->failures[$name]);
    }

    public function isLoaded(string $name): bool
    {
        return isset($this->loaded[$name]) && ! isset($this->failures[$name]);
    }

    public function failure(string $name): ?Throwable
    {
        return $this->failures[$name] ?? null;
    }

    private function autoload(string $dir, Manifest $manifest): void
    {
        if ($manifest->autoload === []) {
            return;
        }

        $loader = array_values(ClassLoader::getRegisteredLoaders())[0] ?? throw new RuntimeException('Composer class loader not found.');

        foreach ($manifest->autoload as $namespace => $relative) {
            $path = ManifestValidator::resolvePath($dir, $relative, directory: true)
                ?? throw new RuntimeException("Autoload path {$relative} is not inside the package.");
            $loader->addPsr4($namespace, $path);
        }
    }

    private function declarations(Manifest $manifest): void
    {
        $registry = $this->app->make(PermissionRegistry::class);
        foreach ($manifest->permissions as $permission) {
            $registry->register($permission['name'], $permission['label'], $permission['group'], $manifest->name);
        }

        $navigation = $this->app->make(AdminNavigation::class);
        foreach ($manifest->adminNavigation as $item) {
            $navigation->add(
                "{$manifest->name}:{$item['key']}",
                $item['title'],
                $manifest->adminRoutePrefix().$item['route'],
                $item['icon'],
                $item['group'],
                $item['permission'],
                $item['order'],
            );
        }
    }

    private function provider(string $name, Manifest $manifest): void
    {
        if ($manifest->provider === null) {
            return;
        }

        $class = $manifest->provider;

        if (! class_exists($class) || ! is_subclass_of($class, ServiceProvider::class)) {
            throw new RuntimeException("Provider {$class} does not exist or is not a service provider.");
        }

        $guarded = new GuardedModuleProvider($this->app, new $class($this->app), fn (Throwable $e) => $this->fail($name, $e));

        // Not Application::register(): it skips a provider whose class is already
        // registered, and every module shares the GuardedModuleProvider class.
        // Module providers boot after the platform's providers.
        $guarded->register();
        $this->app->booted(fn () => $guarded->boot());
    }

    private function routes(string $name, string $dir, Manifest $manifest): void
    {
        if (isset($this->failures[$name]) || $this->app->routesAreCached()) {
            return; // Cached routes already contain the routes of modules enabled when caching.
        }

        try {
            if (isset($manifest->routes['admin'])) {
                $file = ManifestValidator::resolvePath($dir, $manifest->routes['admin'], directory: false)
                    ?? throw new RuntimeException('Admin routes file is missing.');

                Route::middleware(['web', 'installed', 'auth', 'verified', 'staff', 'module.authorized'])
                    ->prefix('admin/'.$manifest->urlPrefix())
                    ->name($manifest->adminRoutePrefix())
                    ->group($file);
            }

            if (isset($manifest->routes['storefront'])) {
                $file = ManifestValidator::resolvePath($dir, $manifest->routes['storefront'], directory: false)
                    ?? throw new RuntimeException('Storefront routes file is missing.');

                Route::middleware(['web', 'installed'])
                    ->prefix($manifest->urlPrefix())
                    ->name($manifest->storefrontRoutePrefix())
                    ->group($file);
            }
        } catch (Throwable $e) {
            $this->fail($name, $e);
        }
    }

    private function fail(string $name, Throwable $e): void
    {
        if (isset($this->failures[$name])) {
            return;
        }

        $this->failures[$name] = $e;
        $this->repository->quarantine($name, $e);
    }
}
