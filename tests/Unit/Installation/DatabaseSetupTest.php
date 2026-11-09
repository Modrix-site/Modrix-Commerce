<?php

namespace Tests\Unit\Installation;

use App\Core\Authorization\Models\Role;
use App\Core\Authorization\SystemRoles;
use App\Core\Installation\DatabaseSetup;
use App\Core\Installation\InstallerException;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\File;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatabaseSetupTest extends TestCase
{
    use RefreshDatabase;

    private string $envDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envDir = sys_get_temp_dir().'/ecomodular-env-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->envDir);
        file_put_contents($this->envDir.'/.env', "APP_NAME=Test\nDB_CONNECTION=sqlite\n");
        $this->app->useEnvironmentPath($this->envDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->envDir);
        parent::tearDown();
    }

    private function env(): string
    {
        return (string) file_get_contents($this->envDir.'/.env');
    }

    /**
     * A DatabaseSetup that never opens a real MySQL connection and keeps the
     * test's in-memory SQLite database.
     */
    public static function fake(string|PDOException $version = '8.0.36'): DatabaseSetup
    {
        return new class($version) extends DatabaseSetup
        {
            public function __construct(private readonly string|PDOException $version) {}

            protected function connect(array $db): string
            {
                if ($this->version instanceof PDOException) {
                    throw new InstallerException('Could not connect to the database: '.$this->redact($this->version->getMessage(), (string) ($db['password'] ?? '')));
                }

                return $this->version;
            }

            protected function useDatabase(array $db, string $driver): void {}

            protected function linkStorage(): bool
            {
                return true;
            }
        };
    }

    /**
     * @return array<string, array{string}>
     */
    public static function envValues(): array
    {
        return [
            'plain' => ['plain'],
            'hash' => ['p@ss#word'],
            'dollar' => ['dollar$sign'],
            'single quote' => ["it's"],
            'both quotes' => ['quote"and\'both'],
            'backslash' => ['back\\slash'],
            'space' => ['with space'],
            'empty' => [''],
            'null keyword' => ['null'],
            'true keyword' => ['True'],
            'empty keyword' => ['(empty)'],
            'single-quoted' => ["'abc'"],
            'double-quoted' => ['"abc"'],
        ];
    }

    #[DataProvider('envValues')]
    public function test_env_values_round_trip(string $value)
    {
        // Read back the way Laravel does: phpdotenv, then Env::get's keyword and quote handling.
        $key = 'INSTALLER_ROUND_TRIP_'.strtoupper(bin2hex(random_bytes(4)));
        app(DatabaseSetup::class)->writeEnv([$key => $value]);

        Dotenv::create(Env::getRepository(), $this->envDir)->load();

        $this->assertSame($value, Env::get($key));
    }

    public function test_write_env_replaces_existing_keys_and_keeps_others()
    {
        app(DatabaseSetup::class)->writeEnv(['DB_CONNECTION' => 'mysql', 'DB_HOST' => 'db.example.test']);

        $parsed = Dotenv::parse($this->env());
        $this->assertSame('mysql', $parsed['DB_CONNECTION']);
        $this->assertSame('db.example.test', $parsed['DB_HOST']);
        $this->assertSame('Test', $parsed['APP_NAME']);
        $this->assertSame(1, substr_count($this->env(), 'DB_CONNECTION='));
    }

    public function test_unwritable_env_throws_installer_exception()
    {
        $this->app->useEnvironmentPath($this->envDir.'/missing/dir');

        $this->expectException(InstallerException::class);
        app(DatabaseSetup::class)->writeEnv(['DB_HOST' => 'x']);
    }

    public function test_driver_thresholds()
    {
        $setup = app(DatabaseSetup::class);

        $this->assertSame('mysql', $setup->driverFor('5.7.44'));
        $this->assertSame('mysql', $setup->driverFor('8.0.36'));
        $this->assertSame('mariadb', $setup->driverFor('10.3.39-MariaDB'));

        foreach (['5.6.51', '10.2.44-MariaDB'] as $old) {
            try {
                $setup->driverFor($old);
                $this->fail("{$old} should be refused");
            } catch (InstallerException $e) {
                $this->assertStringContainsString('too old', $e->getMessage());
            }
        }
    }

    public function test_connection_errors_never_contain_the_password()
    {
        $setup = self::fake(new PDOException("SQLSTATE[HY000] [1045] Access denied for user 'shop' (using password: S3cret!)"));

        try {
            $setup->test(['host' => 'localhost', 'port' => 3306, 'database' => 'shop', 'username' => 'shop', 'password' => 'S3cret!']);
            $this->fail('Expected an InstallerException');
        } catch (InstallerException $e) {
            $this->assertStringContainsString('Access denied', $e->getMessage());
            $this->assertStringNotContainsString('S3cret!', $e->getMessage());
        }
    }

    public function test_prepare_environment_creates_env_and_key()
    {
        $dir = $this->envDir.'/fresh';
        File::ensureDirectoryExists($dir);
        $this->app->useEnvironmentPath($dir);
        config(['app.key' => '']);

        app(DatabaseSetup::class)->prepareEnvironment();

        $this->assertFileExists($dir.'/.env');
        $env = (string) file_get_contents($dir.'/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:.{44}$/m', $env);
        $this->assertStringStartsWith('base64:', config('app.key'));
        // A copied .env.example must not leave a public site in debug mode.
        $this->assertSame('false', Dotenv::parse($env)['APP_DEBUG']);
        $this->assertSame('production', Dotenv::parse($env)['APP_ENV']);
    }

    public function test_prepare_environment_leaves_platform_provided_config_alone()
    {
        // Git platforms inject APP_KEY and the rest as real environment variables, with no .env file.
        $dir = $this->envDir.'/platform';
        File::ensureDirectoryExists($dir);
        $this->app->useEnvironmentPath($dir);
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        app(DatabaseSetup::class)->prepareEnvironment();

        $this->assertFileDoesNotExist($dir.'/.env');
    }

    public function test_apply_writes_env_and_runs_migrations()
    {
        Role::query()->delete();

        $result = self::fake()->apply([
            'host' => 'db.example.test', 'port' => 3306, 'database' => 'shop', 'username' => 'shop', 'password' => 'p@ss#word',
        ]);

        $parsed = Dotenv::parse($this->env());
        $this->assertSame('mysql', $result['driver']);
        $this->assertTrue($result['storage_linked']);
        $this->assertSame('mysql', $parsed['DB_CONNECTION']);
        $this->assertSame('db.example.test', $parsed['DB_HOST']);
        $this->assertSame('shop', $parsed['DB_DATABASE']);
        $this->assertSame('p@ss#word', $parsed['DB_PASSWORD']);
        $this->assertTrue(Role::query()->where('name', SystemRoles::OWNER)->exists());
    }
}
