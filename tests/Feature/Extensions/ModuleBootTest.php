<?php

namespace Tests\Feature\Extensions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Boots the application in separate processes, as a web request or console
 * command would, against a throwaway database. Catches problems that only
 * appear on a fresh boot (e.g. services not yet available while providers
 * register), which in-process tests cannot see.
 */
class ModuleBootTest extends TestCase
{
    use ModuleFixtures;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpModules();
        $this->database = storage_path('framework/testing/boot-'.Str::random(8).'.sqlite');
        File::put($this->database, '');
        $this->beforeApplicationDestroyed(fn () => File::delete($this->database));
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runArtisan(array $arguments): string
    {
        $result = Process::path(base_path())
            ->env([
                'APP_ENV' => 'local',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->database,
                'CACHE_STORE' => 'database',
                'ECOMODULAR_MODULES_PATH' => $this->modulesPath,
            ])
            ->timeout(120)
            ->run([PHP_BINARY, 'artisan', ...$arguments]);

        $this->assertTrue($result->successful(), $result->errorOutput().$result->output());

        return $result->output();
    }

    public function test_an_enabled_module_is_loaded_when_the_application_boots()
    {
        $this->makeHelloModule();

        $this->runArtisan(['migrate', '--force']);
        $this->runArtisan(['ecomodular:sync-permissions']);
        $this->runArtisan(['ecomodular:modules', 'install', 'acme/hello']);
        $this->runArtisan(['ecomodular:modules', 'enable', 'acme/hello', '--trust']);

        // A fresh process: the module's routes exist only if it was loaded during boot.
        $routes = $this->runArtisan(['route:list', '--name=acme.hello', '--json']);
        $this->assertStringContainsString('admin.acme.hello.index', $routes);
        $this->assertStringContainsString('acme.hello.ping', $routes);

        // Safe mode boots without it.
        $safe = Process::path(base_path())
            ->env(['APP_ENV' => 'local', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->database, 'ECOMODULAR_MODULES_PATH' => $this->modulesPath, 'ECOMODULAR_SAFE_MODE' => 'true'])
            ->run([PHP_BINARY, 'artisan', 'route:list', '--name=acme.hello', '--json']);
        $this->assertStringNotContainsString('admin.acme.hello.index', $safe->output());
    }

    public function test_the_sms_module_boots_and_adds_its_gate_to_web_routes()
    {
        File::copyDirectory(base_path('modules/modrixcommerce/sms'), $this->modulesPath.'/modrixcommerce/sms');

        $this->runArtisan(['migrate', '--force']);
        $this->runArtisan(['ecomodular:sync-permissions']);
        $this->runArtisan(['ecomodular:modules', 'install', 'modrixcommerce/sms']);
        $this->runArtisan(['ecomodular:modules', 'enable', 'modrixcommerce/sms', '--trust']);

        $routes = $this->runArtisan(['route:list', '--name=sms', '--json']);
        $this->assertStringContainsString('admin.modrixcommerce.sms.index', $routes);
        $this->assertStringContainsString('modrixcommerce.sms.verify', $routes);

        // The provider booted (not quarantined) and its middleware joined the web group.
        $web = $this->runArtisan(['tinker', '--execute', 'echo json_encode(app("router")->getMiddlewareGroups()["web"]);']);
        $this->assertStringContainsString('RequireVerifiedPhone', $web);
    }
}
