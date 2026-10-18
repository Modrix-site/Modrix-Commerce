<?php

namespace Tests\Feature\Extensions;

use App\Core\Authorization\Models\Permission;
use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\Models\ExtensionEvent;
use App\Core\Extensions\ModuleException;
use App\Core\Extensions\ModuleLoader;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\ModuleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class ModuleLifecycleTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    private ModuleManager $modules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        $this->modules = app(ModuleManager::class);
    }

    private function extension(string $name = 'acme/hello'): Extension
    {
        return Extension::query()->where('name', $name)->firstOrFail();
    }

    public function test_discovery_records_packages_without_installing_or_loading_them()
    {
        $this->makeHelloModule();

        app(ModuleRepository::class)->sync();

        $extension = $this->extension();
        $this->assertSame(ModuleState::Discovered, $extension->state);
        $this->assertNull($extension->version);
        $this->assertSame('1.0.0', $extension->available_version);
        $this->assertFalse(Schema::hasTable('acme_hello_notes'));
        $this->assertFalse(app()->bound('acme.hello.greeter'));
        $this->assertDatabaseHas('extension_events', ['extension' => 'acme/hello', 'action' => 'discovered']);
    }

    public function test_full_lifecycle_install_enable_configure_disable_update_uninstall()
    {
        $this->makeHelloModule();
        $owner = $this->owner();

        // Install: tables and permissions, but no code loaded.
        $this->modules->install('acme/hello', $owner);
        $this->assertSame(ModuleState::Installed, $this->extension()->state);
        $this->assertTrue(Schema::hasTable('acme_hello_notes'));
        $this->assertDatabaseHas('permissions', ['name' => 'hello.manage', 'source' => 'acme/hello']);
        $this->assertFalse(app()->bound('acme.hello.greeter'));

        // Enable: the provider, routes, permission and navigation are live.
        $this->modules->enable('acme/hello', trusted: true, actor: $owner);
        $this->assertSame(ModuleState::Enabled, $this->extension()->state);
        $this->assertSame('Hello from a module', app('acme.hello.greeter')->greet());
        $this->assertTrue(app('acme.hello.booted'));
        $this->assertSame(url('/admin/m/acme/hello'), route('admin.acme.hello.index'));
        $this->actingAs($owner)->get('/admin/m/acme/hello')->assertOk()->assertSee('hello admin');
        $this->get('/m/acme/hello/ping')->assertOk()->assertSee('pong');

        // Configure: typed, validated settings.
        $this->modules->configure('acme/hello', ['greeting' => 'Welcome', 'limit' => '7', 'mode' => 'b'], $owner);
        $manifest = $this->extension()->manifest();
        $this->assertSame(['greeting' => 'Welcome', 'limit' => 7, 'mode' => 'b'], app(ModuleSettingsSchema::class)->values($manifest));

        DB::table('acme_hello_notes')->insert(['body' => 'keep me']);

        // Disable: data and settings are kept.
        $this->modules->disable('acme/hello', $owner);
        $this->assertSame(ModuleState::Disabled, $this->extension()->state);
        $this->assertSame(1, DB::table('acme_hello_notes')->count());
        $this->assertSame('Welcome', app(ModuleSettingsSchema::class)->get($manifest, 'greeting'));

        // New files on disk: update pending, then update runs the new migration.
        $this->makeHelloModule('1.1.0', withSecondMigration: true);
        app(ModuleRepository::class)->sync();
        $this->assertSame(ModuleState::UpdatePending, $this->extension()->state);

        $this->modules->update('acme/hello', $owner);
        $this->assertSame('1.1.0', $this->extension()->version);
        $this->assertSame(ModuleState::Disabled, $this->extension()->state);
        $this->assertTrue(Schema::hasColumn('acme_hello_notes', 'author'));
        $this->assertSame(1, DB::table('acme_hello_notes')->count());

        // Uninstall: requires the typed name; removes tables, settings and permissions.
        try {
            $this->modules->uninstall('acme/hello', 'yes', $owner);
            $this->fail('Uninstall without the typed name must be refused.');
        } catch (ModuleException) {
            $this->assertTrue(Schema::hasTable('acme_hello_notes'));
        }

        $this->modules->uninstall('acme/hello', 'acme/hello', $owner);
        $extension = $this->extension();
        $this->assertSame(ModuleState::Discovered, $extension->state);
        $this->assertNull($extension->version);
        $this->assertFalse(Schema::hasTable('acme_hello_notes'));
        $this->assertDatabaseMissing('permissions', ['name' => 'hello.manage']);
        $this->assertDatabaseMissing('settings', ['key' => 'extension.acme.hello.greeting']);

        $actions = ExtensionEvent::query()->where('extension', 'acme/hello')->where('success', true)->pluck('action')->all();
        foreach (['installed', 'enabled', 'configured', 'disabled', 'updated', 'uninstalled'] as $action) {
            $this->assertContains($action, $actions);
            $this->assertDatabaseHas('audit_logs', ['action' => "extension.{$action}"]);
        }
    }

    public function test_update_of_an_enabled_module_keeps_it_enabled_and_new_code_is_not_loaded_before_migrating()
    {
        $this->makeHelloModule();
        $this->modules->install('acme/hello');
        $this->modules->enable('acme/hello', true);

        $this->makeHelloModule('1.1.0', withSecondMigration: true);

        // At the next boot the loader notices the newer files and does not load them.
        $loader = new ModuleLoader(app(), app(ModuleRepository::class));
        $loader->bootEnabled();
        $this->assertSame(ModuleState::UpdatePending, $this->extension()->state);
        $this->assertFalse($loader->isLoaded('acme/hello'));

        $this->modules->update('acme/hello');
        $this->assertSame(ModuleState::Enabled, $this->extension()->state);
        $this->assertSame('1.1.0', $this->extension()->version);
    }

    public function test_enabling_requires_explicit_trust_confirmation()
    {
        $this->makeHelloModule();
        $this->modules->install('acme/hello');

        $this->expectException(ModuleException::class);
        $this->modules->enable('acme/hello', trusted: false);
    }

    public function test_invalid_transitions_are_refused()
    {
        $this->makeHelloModule();

        foreach ([fn () => $this->modules->enable('acme/hello', true), fn () => $this->modules->disable('acme/hello'), fn () => $this->modules->update('acme/hello'), fn () => $this->modules->uninstall('acme/hello', 'acme/hello')] as $operation) {
            try {
                $operation();
                $this->fail('Expected the operation to be refused for a module that is not installed.');
            } catch (ModuleException) {
                $this->assertSame(ModuleState::Discovered, $this->extension()->state);
            }
        }

        $this->modules->install('acme/hello');
        $this->expectException(ModuleException::class);
        $this->modules->install('acme/hello');
    }

    public function test_dependencies_must_be_installed_and_enabled_and_cannot_be_pulled_away()
    {
        $this->makeHelloModule();
        $this->makeModule('acme/needs-hello', ['requires' => ['acme/hello' => '^1.0']]);

        try {
            $this->modules->install('acme/needs-hello');
            $this->fail('Installing before the dependency must be refused.');
        } catch (ModuleException $e) {
            $this->assertStringContainsString('acme/hello', $e->getMessage());
        }

        $this->modules->install('acme/hello');
        $this->modules->install('acme/needs-hello');

        try {
            $this->modules->enable('acme/needs-hello', true);
            $this->fail('Enabling before the dependency is enabled must be refused.');
        } catch (ModuleException) {
        }

        $this->modules->enable('acme/hello', true);
        $this->modules->enable('acme/needs-hello', true);

        try {
            $this->modules->disable('acme/hello');
            $this->fail('Disabling a dependency of an enabled module must be refused.');
        } catch (ModuleException $e) {
            $this->assertStringContainsString('acme/needs-hello', $e->getMessage());
            $this->assertSame(ModuleState::Enabled, $this->extension()->state);
        }

        $this->modules->disable('acme/needs-hello');
        $this->modules->disable('acme/hello');

        $this->expectException(ModuleException::class);
        $this->modules->uninstall('acme/hello', 'acme/hello'); // acme/needs-hello is still installed
    }

    public function test_dependency_versions_are_enforced()
    {
        $this->makeHelloModule();
        $this->makeModule('acme/needs-new-hello', ['requires' => ['acme/hello' => '^2.0']]);
        $this->modules->install('acme/hello');

        $this->expectExceptionMessage('Requires acme/hello ^2.0; v1.0.0 is installed.');
        $this->modules->install('acme/needs-new-hello');
    }

    public function test_a_module_that_throws_while_starting_is_not_enabled()
    {
        $this->makeModule('acme/broken', [
            'autoload' => ['psr-4' => ['Acme\\Broken\\' => 'src/']],
            'provider' => 'Acme\\Broken\\ModuleServiceProvider',
        ], <<<'PHP'
            <?php

            namespace Acme\Broken;

            use Illuminate\Support\ServiceProvider;

            class ModuleServiceProvider extends ServiceProvider
            {
                public function boot(): void
                {
                    throw new \RuntimeException('database credentials missing');
                }
            }
            PHP);

        $this->modules->install('acme/broken');

        try {
            $this->modules->enable('acme/broken', true);
            $this->fail('Enabling a module that fails to boot must fail.');
        } catch (ModuleException $e) {
            $this->assertStringContainsString('database credentials missing', $e->getMessage());
        }

        $extension = $this->extension('acme/broken');
        $this->assertSame(ModuleState::Failed, $extension->state);
        $this->assertStringContainsString('database credentials missing', (string) $extension->failure);
        $this->assertDatabaseHas('extension_events', ['extension' => 'acme/broken', 'action' => 'quarantined', 'success' => false]);

        // The store still works.
        $this->get('/')->assertOk();
    }

    public function test_a_module_that_starts_failing_at_boot_is_quarantined_and_the_store_keeps_working()
    {
        $this->makeModule('acme/flaky', [
            'autoload' => ['psr-4' => ['Acme\\Flaky\\' => 'src/']],
            'provider' => 'Acme\\Flaky\\ModuleServiceProvider',
        ], <<<'PHP'
            <?php

            namespace Acme\Flaky;

            use Illuminate\Support\ServiceProvider;

            class ModuleServiceProvider extends ServiceProvider
            {
                public function register(): void
                {
                    if (config('acme.flaky.fail')) {
                        throw new \LogicException('flaky failure');
                    }
                }
            }
            PHP);

        $this->modules->install('acme/flaky');
        $this->modules->enable('acme/flaky', true);
        config(['acme.flaky.fail' => true]);

        // Simulates the next request booting the application.
        $loader = new ModuleLoader(app(), app(ModuleRepository::class));
        $loader->bootEnabled();

        $this->assertFalse($loader->isLoaded('acme/flaky'));
        $this->assertSame(ModuleState::Failed, $this->extension('acme/flaky')->state);
        $this->assertDatabaseHas('audit_logs', ['action' => 'extension.quarantined']);
        $this->get('/')->assertOk();

        // Recovery: disable, fix, enable again.
        $this->modules->disable('acme/flaky');
        config(['acme.flaky.fail' => false]);
        $this->assertSame(ModuleState::Enabled, $this->modules->enable('acme/flaky', true)->state);
    }

    public function test_a_failed_install_is_rolled_back_and_can_be_retried()
    {
        $dir = $this->makeHelloModule();
        $this->writeFile($dir.'/database/migrations/2026_03_01_000000_broken_acme_hello.php', <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;

            return new class extends Migration
            {
                public function up(): void
                {
                    throw new \RuntimeException('migration exploded');
                }

                public function down(): void {}
            };
            PHP);

        try {
            $this->modules->install('acme/hello');
            $this->fail('A failing migration must fail the install.');
        } catch (ModuleException $e) {
            $this->assertStringContainsString('migration exploded', $e->getMessage());
            $this->assertStringContainsString('rolled back', $e->getMessage());
        }

        $this->assertSame(ModuleState::Failed, $this->extension()->state);
        $this->assertNull($this->extension()->version);
        $this->assertFalse(Schema::hasTable('acme_hello_notes'));

        File::delete($dir.'/database/migrations/2026_03_01_000000_broken_acme_hello.php');
        $this->assertSame(ModuleState::Installed, $this->modules->install('acme/hello')->state);
    }

    public function test_a_failed_update_keeps_the_old_version_and_can_be_retried()
    {
        $dir = $this->makeHelloModule();
        $this->modules->install('acme/hello');
        $this->modules->enable('acme/hello', true);

        $this->makeHelloModule('1.1.0');
        $this->writeFile($dir.'/database/migrations/2026_03_01_000000_broken_acme_hello.php', <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;

            return new class extends Migration
            {
                public function up(): void
                {
                    throw new \RuntimeException('update exploded');
                }

                public function down(): void {}
            };
            PHP);

        try {
            $this->modules->update('acme/hello');
            $this->fail('A failing update migration must fail the update.');
        } catch (ModuleException $e) {
            $this->assertStringContainsString('stays at v1.0.0', $e->getMessage());
        }

        $this->assertSame(ModuleState::Failed, $this->extension()->state);
        $this->assertSame('1.0.0', $this->extension()->version);

        File::delete($dir.'/database/migrations/2026_03_01_000000_broken_acme_hello.php');
        $updated = $this->modules->update('acme/hello');
        $this->assertSame('1.1.0', $updated->version);
        $this->assertSame(ModuleState::Enabled, $updated->state);
    }

    public function test_downgraded_or_missing_files_take_an_installed_module_out_of_service()
    {
        $dir = $this->makeHelloModule('1.2.0');
        $this->modules->install('acme/hello');

        $this->makeHelloModule('1.0.0');
        app(ModuleRepository::class)->sync();
        $this->assertSame(ModuleState::Failed, $this->extension()->state);
        $this->assertStringContainsString('older than the installed version', (string) $this->extension()->failure);

        File::deleteDirectory($dir);
        app(ModuleRepository::class)->sync();
        $this->assertSame(ModuleState::Failed, $this->extension()->state);
        $this->assertSame('1.2.0', $this->extension()->version);
    }

    public function test_settings_are_validated_by_type()
    {
        $this->makeHelloModule();
        $this->modules->install('acme/hello');

        $this->expectException(ValidationException::class);
        $this->modules->configure('acme/hello', ['greeting' => 'Hi', 'limit' => '50', 'mode' => 'z']);
    }

    public function test_settings_hidden_by_visible_when_are_optional_and_keep_their_values()
    {
        $this->makeHelloModule(overrides: ['settings' => [
            ['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'default' => 'a', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
            ['key' => 'a_model', 'type' => 'string', 'label' => 'A model', 'required' => true, 'default' => 'a-1', 'suggestions' => ['a-1', 'a-2'], 'visible_when' => ['mode' => 'a']],
            ['key' => 'b_model', 'type' => 'string', 'label' => 'B model', 'required' => true, 'default' => 'b-1', 'visible_when' => ['mode' => ['b']]],
        ]]);
        $this->modules->install('acme/hello');
        $settings = app(ModuleSettingsSchema::class);
        $manifest = app(ModuleRepository::class)->find('acme/hello')->manifest();

        // b_model is hidden while mode is "a": empty is allowed and the stored value is kept.
        $this->modules->configure('acme/hello', ['mode' => 'a', 'a_model' => 'a-custom-snapshot', 'b_model' => '']);
        $this->assertSame(['mode' => 'a', 'a_model' => 'a-custom-snapshot', 'b_model' => 'b-1'], $settings->values($manifest));

        // A visible required field is still required.
        try {
            $this->modules->configure('acme/hello', ['mode' => 'b', 'a_model' => '', 'b_model' => '']);
            $this->fail('A visible required setting accepted an empty value.');
        } catch (ValidationException $e) {
            $this->assertSame(['b_model'], array_keys($e->errors()));
        }
        $this->assertSame('a-custom-snapshot', $settings->values($manifest)['a_model']);
    }

    public function test_invalid_visible_when_and_suggestions_are_rejected()
    {
        $this->makeHelloModule(overrides: ['settings' => [
            ['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => [['value' => 'a', 'label' => 'A']]],
            ['key' => 'limit', 'type' => 'integer', 'label' => 'Limit', 'suggestions' => ['1']],
            ['key' => 'model', 'type' => 'string', 'label' => 'Model', 'visible_when' => ['missing' => 'a']],
            ['key' => 'other', 'type' => 'string', 'label' => 'Other', 'visible_when' => ['a']],
        ]]);

        app(ModuleRepository::class)->sync();

        $extension = $this->extension();
        $this->assertSame(ModuleState::Invalid, $extension->state);
        $errors = implode("\n", (array) $extension->errors);
        $this->assertStringContainsString('settings[1].suggestions', $errors);
        $this->assertStringContainsString('settings[2].visible_when refers to unknown setting missing', $errors);
        $this->assertStringContainsString('settings[3].visible_when must map setting keys', $errors);
    }

    public function test_module_admin_routes_require_a_declared_permission()
    {
        $this->makeHelloModule();
        $this->modules->install('acme/hello');
        $this->modules->enable('acme/hello', true);

        $this->get('/admin/m/acme/hello')->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('analyst'))->get('/admin/m/acme/hello')->assertForbidden();
        // A module route without a can: middleware is refused even for owners.
        $this->actingAs($this->owner())->get('/admin/m/acme/hello/open')->assertForbidden();
    }

    public function test_disabled_module_permissions_are_kept_but_have_no_effect()
    {
        $this->makeHelloModule();
        $this->modules->install('acme/hello');
        $this->modules->enable('acme/hello', true);
        $role = $this->roleWithPermissions('hello-staff', ['hello.manage']);

        $this->modules->disable('acme/hello');

        $this->assertTrue($role->permissions()->where('name', 'hello.manage')->exists());
        $this->assertSame(1, Permission::query()->where('source', 'acme/hello')->count());
    }

    public function test_safe_mode_loads_nothing_and_cli_still_manages_modules()
    {
        $this->makeHelloModule();
        $this->modules->install('acme/hello');
        $this->modules->enable('acme/hello', true);
        config(['ecomodular.modules.safe_mode' => true]);

        $loader = new ModuleLoader(app(), app(ModuleRepository::class));
        $loader->bootEnabled();
        $this->assertFalse($loader->isLoaded('acme/hello'));

        $this->artisan('ecomodular:modules', ['action' => 'disable', 'name' => 'acme/hello'])
            ->expectsOutputToContain('Safe mode is on')
            ->assertSuccessful();
        $this->assertSame(ModuleState::Disabled, $this->extension()->state);
    }

    public function test_cli_lists_and_drives_the_lifecycle()
    {
        $this->makeHelloModule();

        $this->artisan('ecomodular:modules')->expectsOutputToContain('acme/hello')->assertSuccessful();
        $this->artisan('ecomodular:modules', ['action' => 'install', 'name' => 'acme/hello'])->assertSuccessful();
        $this->artisan('ecomodular:modules', ['action' => 'enable', 'name' => 'acme/hello'])->assertFailed();
        $this->artisan('ecomodular:modules', ['action' => 'enable', 'name' => 'acme/hello', '--trust' => true])->assertSuccessful();
        $this->artisan('ecomodular:modules', ['action' => 'uninstall', 'name' => 'acme/hello'])->assertFailed();
    }
}
