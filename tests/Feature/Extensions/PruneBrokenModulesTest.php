<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * `php artisan ecomodular:modules:prune` clears out modules a server can no
 * longer run (files missing, partly deleted or older than the installed
 * version) so they can be installed again from Modrix Cloud.
 */
class PruneBrokenModulesTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
    }

    private function enabledHello(string $version = '1.0.0'): void
    {
        $this->makeHelloModule($version);
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('acme/hello');
        app(ModuleManager::class)->enable('acme/hello', true);
    }

    private function state(): ?ModuleState
    {
        return Extension::query()->where('name', 'acme/hello')->first()?->state;
    }

    public function test_without_force_it_only_lists_what_it_would_remove(): void
    {
        $this->enabledHello();
        File::deleteDirectory($this->modulesPath.'/acme/hello/src');

        $this->artisan('ecomodular:modules:prune')
            ->expectsOutputToContain('acme/hello')
            ->expectsOutputToContain('--force')
            ->assertSuccessful();

        $this->assertSame(ModuleState::Failed, $this->state());
        $this->assertDirectoryExists($this->modulesPath.'/acme/hello');
    }

    public function test_it_removes_a_module_whose_files_were_partly_deleted(): void
    {
        $this->enabledHello();
        File::deleteDirectory($this->modulesPath.'/acme/hello/src');

        $this->artisan('ecomodular:modules:prune', ['--force' => true])->assertSuccessful();

        $this->assertNull($this->state());
        $this->assertDirectoryDoesNotExist($this->modulesPath.'/acme/hello');
    }

    public function test_it_removes_a_module_whose_files_are_gone(): void
    {
        $this->enabledHello();
        File::deleteDirectory($this->modulesPath.'/acme/hello');

        $this->artisan('ecomodular:modules:prune', ['--force' => true])->assertSuccessful();

        $this->assertNull($this->state());
    }

    public function test_it_removes_a_module_whose_files_are_older_than_the_installed_version(): void
    {
        $this->enabledHello('1.0.1');
        File::deleteDirectory($this->modulesPath.'/acme/hello');
        $this->makeHelloModule('1.0.0');

        $this->artisan('ecomodular:modules:prune', ['--force' => true])->assertSuccessful();

        $this->assertNull($this->state());
        $this->assertDirectoryDoesNotExist($this->modulesPath.'/acme/hello');
    }

    public function test_working_modules_are_left_alone(): void
    {
        $this->enabledHello();

        $this->artisan('ecomodular:modules:prune', ['--force' => true])
            ->expectsOutputToContain('Nothing to remove')
            ->assertSuccessful();

        $this->assertSame(ModuleState::Enabled, $this->state());
        $this->assertDirectoryExists($this->modulesPath.'/acme/hello');
    }
}
