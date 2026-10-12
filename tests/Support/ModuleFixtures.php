<?php

namespace Tests\Support;

use App\Core\Extensions\ModuleRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Writes real module packages into a throwaway modules directory so the
 * lifecycle is exercised end to end (discovery, validation, migrations,
 * autoloading, provider boot, routes).
 */
trait ModuleFixtures
{
    protected string $modulesPath;

    protected function setUpModules(): void
    {
        $this->modulesPath = storage_path('framework/testing/modules-'.Str::random(10));
        File::ensureDirectoryExists($this->modulesPath);
        config(['ecomodular.modules.path' => $this->modulesPath]);
        ModuleRepository::flushBootCache();

        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->modulesPath));
    }

    /**
     * The standard sample package "acme/hello": a table, a permission, a
     * setting, an admin page with navigation, and a provider that binds a
     * service and listens to an event.
     *
     * @param  array<string, mixed>  $overrides  manifest keys to replace (null removes a key)
     */
    protected function makeHelloModule(string $version = '1.0.0', array $overrides = [], bool $withSecondMigration = false): string
    {
        $dir = $this->modulesPath.'/acme/hello';

        $this->writeFile($dir.'/src/HelloServiceProvider.php', <<<'PHP'
            <?php

            namespace Acme\Hello;

            use Illuminate\Support\ServiceProvider;

            class HelloServiceProvider extends ServiceProvider
            {
                public function register(): void
                {
                    $this->app->singleton('acme.hello.greeter', fn () => new Greeter);
                }

                public function boot(): void
                {
                    $this->app->instance('acme.hello.booted', true);
                }
            }
            PHP);

        $this->writeFile($dir.'/src/Greeter.php', <<<'PHP'
            <?php

            namespace Acme\Hello;

            class Greeter
            {
                public function greet(): string
                {
                    return 'Hello from a module';
                }
            }
            PHP);

        $this->writeFile($dir.'/routes/admin.php', <<<'PHP'
            <?php

            use Illuminate\Support\Facades\Route;

            Route::get('/', fn () => 'hello admin')->middleware('can:hello.manage')->name('index');
            Route::get('open', fn () => 'no permission declared')->name('open');
            PHP);

        $this->writeFile($dir.'/routes/storefront.php', <<<'PHP'
            <?php

            use Illuminate\Support\Facades\Route;

            Route::get('ping', fn () => 'pong')->name('ping');
            PHP);

        $this->writeFile($dir.'/database/migrations/2026_01_01_000000_create_acme_hello_notes_table.php', <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('acme_hello_notes', function (Blueprint $table) {
                        $table->id();
                        $table->string('body');
                    });
                }

                public function down(): void
                {
                    Schema::dropIfExists('acme_hello_notes');
                }
            };
            PHP);

        if ($withSecondMigration) {
            $this->writeFile($dir.'/database/migrations/2026_02_01_000000_add_author_to_acme_hello_notes.php', <<<'PHP'
                <?php

                use Illuminate\Database\Migrations\Migration;
                use Illuminate\Database\Schema\Blueprint;
                use Illuminate\Support\Facades\Schema;

                return new class extends Migration
                {
                    public function up(): void
                    {
                        Schema::table('acme_hello_notes', function (Blueprint $table) {
                            $table->string('author')->nullable();
                        });
                    }

                    public function down(): void
                    {
                        Schema::table('acme_hello_notes', function (Blueprint $table) {
                            $table->dropColumn('author');
                        });
                    }
                };
                PHP);
        }

        return $this->writeManifest('acme/hello', array_merge([
            'name' => 'acme/hello',
            'display_name' => 'Hello',
            'description' => 'Test module.',
            'version' => $version,
            'type' => 'module',
            'platform_version' => '^0.4 || ^1.0',
            'autoload' => ['psr-4' => ['Acme\\Hello\\' => 'src/']],
            'provider' => 'Acme\\Hello\\HelloServiceProvider',
            'permissions' => [['name' => 'hello.manage', 'label' => 'Manage hello', 'group' => 'Hello']],
            'capabilities' => ['admin.routes', 'storefront.routes', 'admin.navigation', 'database.migrations', 'settings', 'permissions'],
            'migrations' => 'database/migrations',
            'routes' => ['admin' => 'routes/admin.php', 'storefront' => 'routes/storefront.php'],
            'settings' => [
                ['key' => 'greeting', 'type' => 'string', 'label' => 'Greeting', 'default' => 'Hi'],
                ['key' => 'limit', 'type' => 'integer', 'label' => 'Limit', 'default' => 5, 'min' => 1, 'max' => 10],
                ['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'default' => 'a', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
            ],
            'admin_navigation' => [['key' => 'hello', 'title' => 'Hello', 'route' => 'index', 'icon' => 'smile', 'group' => 'Extensions', 'permission' => 'hello.manage']],
        ], $overrides));
    }

    /**
     * A package with only a manifest (plus a provider when given).
     *
     * @param  array<string, mixed>  $manifest
     */
    protected function makeModule(string $name, array $manifest, ?string $providerSource = null): string
    {
        if ($providerSource !== null) {
            $this->writeFile($this->modulesPath.'/'.$name.'/src/ModuleServiceProvider.php', $providerSource);
        }

        return $this->writeManifest($name, array_merge([
            'name' => $name,
            'display_name' => Str::headline(Str::after($name, '/')),
            'version' => '1.0.0',
            'type' => 'module',
            'platform_version' => '^0.4 || ^1.0',
        ], $manifest));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function writeManifest(string $name, array $manifest): string
    {
        $manifest = array_filter($manifest, fn ($value) => $value !== null);
        $this->writeFile($this->modulesPath.'/'.$name.'/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $this->modulesPath.'/'.$name;
    }

    protected function writeFile(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }
}
