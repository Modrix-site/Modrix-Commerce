<?php

namespace Tests\Feature\Install;

use App\Core\Installation\DatabaseSetup;
use App\Core\Installation\InstallerException;
use App\Core\Installation\InstallerToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrepareInstallEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(InstallerToken::class)->delete();
        parent::tearDown();
    }

    public function test_installer_renders_with_no_database_and_database_session_driver()
    {
        config(['session.driver' => 'database', 'cache.default' => 'database']);
        Schema::drop('settings');
        Schema::drop('sessions');
        Schema::drop('cache');

        $this->get('/install')->assertOk();

        $this->assertSame('file', config('session.driver'));
        $this->assertSame('file', config('cache.default'));
    }

    public function test_drivers_are_untouched_once_installed()
    {
        $this->installPlatform();
        config(['session.driver' => 'array', 'cache.default' => 'database']);

        $this->get('/');

        $this->assertSame('database', config('cache.default'));
    }

    public function test_unwritable_env_returns_a_readable_500()
    {
        $this->app->instance(DatabaseSetup::class, new class extends DatabaseSetup
        {
            public function prepareEnvironment(): void
            {
                throw new InstallerException('Make .env writable');
            }
        });

        $this->get('/install')->assertStatus(500)->assertSee('Make .env writable');
    }
}
