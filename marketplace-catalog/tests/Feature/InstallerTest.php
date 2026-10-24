<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Marketplace\Models\User;
use Marketplace\Services\Installer;
use Marketplace\Services\InstallerException;
use Tests\TestCase;

/**
 * The web installer at /install: MySQL/MariaDB connection, then the first
 * administrator. Every page leads there until it is done; then it is gone.
 */
class InstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $envDir;

    protected function setUp(): void
    {
        parent::setUp();
        unlink(config('marketplace.installed_file'));
        $this->envDir = sys_get_temp_dir().'/mk-env-'.bin2hex(random_bytes(4));
        mkdir($this->envDir);
        file_put_contents($this->envDir.'/.env', "APP_NAME=\"Modrix Marketplace\"\nAPP_KEY=base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=\nDB_CONNECTION=mysql\nDB_HOST=\nDB_DATABASE=\n");
        $this->app->useEnvironmentPath($this->envDir);
    }

    protected function tearDown(): void
    {
        $this->app['files']->deleteDirectory($this->envDir);
        parent::tearDown();
    }

    /** A server that accepts the connection; migrations run on the test database. */
    private function fakeServer(string $version = '8.0.36'): void
    {
        $this->app->instance(Installer::class, new class($version) extends Installer
        {
            public function __construct(private string $version) {}

            public function connect(array $db): string
            {
                return $this->version;
            }

            protected function useDatabase(array $db, string $driver): void {}
        });
    }

    private function database(array $overrides = [])
    {
        return $this->post('/install/database', array_merge(['host' => 'db.internal', 'port' => '3306', 'database' => 'marketplace', 'username' => 'mk_user', 'password' => "p'a\"s\$s\\w#rd"], $overrides));
    }

    private function env(): array
    {
        return Dotenv::parse((string) file_get_contents($this->envDir.'/.env'));
    }

    public function test_every_page_leads_to_the_installer_until_it_is_done()
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/admin')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('MySQL');
        $this->get('/up')->assertOk();
    }

    public function test_a_database_that_cannot_be_reached_is_refused()
    {
        $this->database(['host' => '127.0.0.1', 'port' => '1'])->assertSessionHasErrors('host');

        $this->assertSame('', $this->env()['DB_HOST']);
    }

    public function test_old_mysql_servers_are_refused()
    {
        $this->fakeServer('5.6.51');

        $this->database()->assertSessionHasErrors('host');
    }

    public function test_the_database_step_saves_the_connection_and_creates_the_tables()
    {
        $this->fakeServer();

        $this->database()->assertRedirect('/install');

        $env = $this->env();
        $this->assertSame('mysql', $env['DB_CONNECTION']);
        $this->assertSame('db.internal', $env['DB_HOST']);
        $this->assertSame('3306', $env['DB_PORT']);
        $this->assertSame('marketplace', $env['DB_DATABASE']);
        $this->assertSame('mk_user', $env['DB_USERNAME']);
        $this->assertSame("p'a\"s\$s\\w#rd", $env['DB_PASSWORD']);
        $this->assertSame('Modrix Marketplace', $env['APP_NAME']);
        $this->get('/install')->assertSee('Admin account');
    }

    public function test_mariadb_servers_use_the_mariadb_driver()
    {
        $this->fakeServer('10.6.18-MariaDB');

        $this->database()->assertRedirect('/install');

        $this->assertSame('mariadb', $this->env()['DB_CONNECTION']);
    }

    public function test_the_admin_step_needs_the_database_step_first()
    {
        $this->post('/install/admin', ['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'Long-enough-pass-1'])->assertRedirect('/install');

        $this->assertSame(0, User::query()->count());
    }

    public function test_finishing_creates_the_admin_and_locks_the_installer()
    {
        $this->fakeServer();
        $this->database();

        $this->post('/install/admin', ['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'short'])->assertSessionHasErrors('password');
        $this->post('/install/admin', ['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'Long-enough-pass-1', 'password_confirmation' => 'Long-enough-pass-1'])->assertOk()->assertSee('/admin/login');

        $this->assertTrue(User::query()->where('email', 'owner@example.test')->value('is_admin'));
        $this->assertFileExists(config('marketplace.installed_file'));
        $this->get('/install')->assertNotFound();
        $this->post('/install/database', [])->assertNotFound();
        $this->post('/install/admin', [])->assertNotFound();
        $this->get('/')->assertOk();
    }

    public function test_an_imported_admin_finishes_without_a_new_account()
    {
        $this->fakeServer();
        User::factory()->admin()->create();
        $this->database();

        $this->get('/install')->assertSee('already has an administrator');
        $this->post('/install/admin', [])->assertOk();

        $this->assertSame(1, User::query()->count());
        $this->assertFileExists(config('marketplace.installed_file'));
    }

    public function test_the_install_command_marks_the_app_installed()
    {
        $this->artisan('marketplace:install', ['--name' => 'Owner', '--email' => 'owner@example.test', '--password' => 'Long-enough-pass-1'])->assertSuccessful();

        $this->assertFileExists(config('marketplace.installed_file'));
    }

    public function test_installer_errors_are_reported_on_the_host_field()
    {
        $this->app->instance(Installer::class, new class extends Installer
        {
            public function connect(array $db): string
            {
                throw new InstallerException('Access denied for user');
            }
        });

        $this->database()->assertSessionHasErrors(['host' => 'Access denied for user']);
    }
}
