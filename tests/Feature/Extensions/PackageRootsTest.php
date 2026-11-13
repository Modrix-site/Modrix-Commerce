<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use App\Core\Extensions\PackageRoots;
use App\Core\Themes\ThemeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Installed packages live under storage/ (the install root) and survive a
 * redeploy that replaces the code; packages shipped with the code stay in
 * the bundled root. The store reads both.
 */
class PackageRootsTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    private string $bundled;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();

        $this->bundled = storage_path('framework/testing/bundled-'.Str::random(10));
        File::ensureDirectoryExists($this->bundled);
        config(['ecomodular.modules.bundled_path' => $this->bundled]);
        PackageRoots::forget();
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->bundled));
    }

    private function moveToBundled(string $name = 'acme/hello'): void
    {
        File::ensureDirectoryExists(dirname($this->bundled.'/'.$name));
        File::moveDirectory($this->modulesPath.'/'.$name, $this->bundled.'/'.$name);
        PackageRoots::forget();
    }

    private function setVersion(string $dir, string $version): void
    {
        $file = $dir.'/module.json';
        $data = json_decode((string) file_get_contents($file), true);
        $data['version'] = $version;
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        PackageRoots::forget();
    }

    public function test_the_default_install_roots_are_under_storage(): void
    {
        $config = require config_path('ecomodular.php');

        $this->assertSame(storage_path('app/packages/modules'), $config['modules']['path']);
        $this->assertSame(base_path('modules'), $config['modules']['bundled_path']);
        $this->assertSame(storage_path('app/packages/themes'), $config['themes']['path']);
        $this->assertSame(base_path('themes'), $config['themes']['bundled_path']);
    }

    public function test_a_module_in_the_bundled_root_is_discovered_and_installed(): void
    {
        $this->makeHelloModule();
        $this->moveToBundled();

        app(ModuleRepository::class)->sync();

        $this->assertSame(['acme/hello'], Extension::query()->where('type', 'module')->pluck('name')->all());
        $this->assertSame(realpath($this->bundled.'/acme/hello'), realpath(ModuleRepository::packageDir('acme/hello')));

        $owner = $this->owner();
        app(ModuleManager::class)->install('acme/hello', $owner);
        app(ModuleManager::class)->enable('acme/hello', true, $owner);
        $this->assertSame(ModuleState::Enabled, Extension::query()->where('name', 'acme/hello')->value('state'));
    }

    public function test_the_newer_copy_wins_and_the_install_root_wins_a_tie(): void
    {
        $this->makeHelloModule('1.0.0');
        File::copyDirectory($this->modulesPath.'/acme/hello', $this->bundled.'/acme/hello');
        PackageRoots::forget();

        $this->assertSame(realpath($this->modulesPath.'/acme/hello'), realpath(ModuleRepository::packageDir('acme/hello')));

        // A core update ships a newer bundled copy: it takes over.
        $this->setVersion($this->bundled.'/acme/hello', '1.1.0');
        $this->assertSame(realpath($this->bundled.'/acme/hello'), realpath(ModuleRepository::packageDir('acme/hello')));

        // A newer install from Modrix Cloud takes over again.
        $this->setVersion($this->modulesPath.'/acme/hello', '1.2.0');
        $this->assertSame(realpath($this->modulesPath.'/acme/hello'), realpath(ModuleRepository::packageDir('acme/hello')));
    }

    public function test_new_installs_are_placed_in_the_install_root(): void
    {
        $this->assertSame(
            $this->modulesPath.DIRECTORY_SEPARATOR.'acme'.DIRECTORY_SEPARATOR.'fresh',
            ModuleRepository::packageDir('acme/fresh'),
        );
    }

    public function test_a_module_whose_files_come_back_is_enabled_again(): void
    {
        $this->makeHelloModule();
        $owner = $this->owner();
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('acme/hello', $owner);
        app(ModuleManager::class)->enable('acme/hello', true, $owner);

        // A redeploy wiped the files.
        File::moveDirectory($this->modulesPath.'/acme/hello', $this->bundled.'/parked');
        PackageRoots::forget();
        app(ModuleRepository::class)->sync();
        $missing = Extension::query()->where('name', 'acme/hello')->firstOrFail();
        $this->assertSame(ModuleState::Failed, $missing->state);
        $this->assertStringStartsWith(ModuleRepository::FILES_MISSING, (string) $missing->failure);

        // Reinstalled with the same version: back to enabled, nothing to redo.
        File::moveDirectory($this->bundled.'/parked', $this->modulesPath.'/acme/hello');
        PackageRoots::forget();
        app(ModuleRepository::class)->sync();
        $restored = Extension::query()->where('name', 'acme/hello')->firstOrFail();
        $this->assertSame(ModuleState::Enabled, $restored->state);
        $this->assertNull($restored->failure);
    }

    public function test_restored_files_with_a_newer_version_wait_for_the_update(): void
    {
        $this->makeHelloModule();
        $owner = $this->owner();
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('acme/hello', $owner);
        app(ModuleManager::class)->enable('acme/hello', true, $owner);

        File::deleteDirectory($this->modulesPath.'/acme/hello');
        PackageRoots::forget();
        app(ModuleRepository::class)->sync();

        $this->makeHelloModule('1.1.0');
        PackageRoots::forget();
        app(ModuleRepository::class)->sync();
        $this->assertSame(ModuleState::UpdatePending, Extension::query()->where('name', 'acme/hello')->value('state'));

        app(ModuleManager::class)->update('acme/hello', $owner);
        $updated = Extension::query()->where('name', 'acme/hello')->firstOrFail();
        $this->assertSame(ModuleState::Enabled, $updated->state);
        $this->assertSame('1.1.0', $updated->version);
    }

    public function test_a_module_in_the_install_root_can_be_deleted(): void
    {
        $this->makeHelloModule();
        $owner = $this->owner();
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('acme/hello', $owner);

        app(ModuleManager::class)->delete('acme/hello', 'acme/hello', $owner);

        $this->assertDirectoryDoesNotExist($this->modulesPath.'/acme/hello');
        $this->assertDatabaseMissing('extensions', ['name' => 'acme/hello']);
    }

    public function test_themes_are_read_from_both_roots(): void
    {
        $install = storage_path('framework/testing/theme-install-'.Str::random(10));
        File::ensureDirectoryExists($install);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($install));
        File::copyDirectory(base_path('themes/modrixcommerce/circuit'), $install.'/acme/sunny');
        $manifest = json_decode((string) file_get_contents($install.'/acme/sunny/theme.json'), true);
        $manifest['name'] = 'acme/sunny';
        file_put_contents($install.'/acme/sunny/theme.json', json_encode($manifest, JSON_PRETTY_PRINT));

        config(['ecomodular.themes.path' => $install, 'ecomodular.themes.bundled_path' => base_path('themes')]);
        PackageRoots::forget();
        app(ThemeRepository::class)->sync();

        $names = Extension::query()->where('type', 'theme')->pluck('name')->all();
        $this->assertContains('acme/sunny', $names);
        $this->assertContains('modrixcommerce/circuit', $names);
        $this->assertSame(realpath($install.'/acme/sunny'), realpath(ThemeRepository::packageDir('acme/sunny')));
        $this->assertSame(realpath(base_path('themes/modrixcommerce/circuit')), realpath(ThemeRepository::packageDir('modrixcommerce/circuit')));
    }
}
