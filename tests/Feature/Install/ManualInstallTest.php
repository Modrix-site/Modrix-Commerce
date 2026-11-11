<?php

namespace Tests\Feature\Install;

use App\Core\Installation\DatabaseSetup;
use App\Core\Installation\InstallationState;
use App\Core\Installation\InstallerToken;
use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use PDOException;
use Tests\TestCase;
use Tests\Unit\Installation\DatabaseSetupTest;

/**
 * Shared hosting (cPanel, Plesk, DirectAdmin...): requirements, then the
 * database step writes .env and migrates, then the owner account.
 */
class ManualInstallTest extends TestCase
{
    use RefreshDatabase;

    private string $envDir;

    private InstallerToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        // The tests assume no database password unless they set one (CI's MySQL job has one).
        config(['database.connections.'.config('database.default').'.password' => '']);
        config(['ecomodular.installer.mode' => 'manual']);

        $this->envDir = sys_get_temp_dir().'/ecomodular-env-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->envDir);
        file_put_contents($this->envDir.'/.env', "APP_NAME=Test\nDB_CONNECTION=sqlite\n");
        $this->app->useEnvironmentPath($this->envDir);

        $this->app->instance(DatabaseSetup::class, DatabaseSetupTest::fake());
        $this->withServerVariables(['SCRIPT_FILENAME' => public_path('index.php'), 'DOCUMENT_ROOT' => public_path()]);

        $this->token = app(InstallerToken::class);
        $this->token->delete();
    }

    protected function tearDown(): void
    {
        $this->token->delete();
        File::deleteDirectory($this->envDir);
        parent::tearDown();
    }

    /**
     * @return array<string, string|int>
     */
    private function database(array $overrides = []): array
    {
        return array_merge([
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'shop',
            'username' => 'shop_user',
            'db_password' => 'p@ss#word',
            'setup_token' => $this->token->ensure(),
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function ownerPayload(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'Panel Store',
            'default_currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
            'name' => 'Panel Owner',
            'email' => 'panel@example.com',
            'password' => 'a-Very-strong-passphrase-42',
            'password_confirmation' => 'a-Very-strong-passphrase-42',
        ], $overrides);
    }

    public function test_manual_mode_starts_at_the_database_step()
    {
        $this->get(route('install.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'manual')
            ->where('step', 'database')
            ->where('servedFromPublic', true));
    }

    public function test_document_root_outside_public_blocks_the_database_step()
    {
        $this->withServerVariables(['SCRIPT_FILENAME' => public_path('index.php'), 'SCRIPT_NAME' => '/public/index.php', 'DOCUMENT_ROOT' => base_path()]);
        $before = (string) file_get_contents($this->envDir.'/.env');

        $this->get(route('install.show'))->assertInertia(fn (Assert $page) => $page
            ->where('step', 'requirements')
            ->where('servedFromPublic', false));

        $this->post(route('install.database'), $this->database())->assertSessionHasErrors('host');
        $this->assertSame($before, (string) file_get_contents($this->envDir.'/.env'));
    }

    public function test_database_step_requires_the_setup_token()
    {
        $this->post(route('install.database'), $this->database(['setup_token' => 'wrong']))
            ->assertSessionHasErrors('setup_token');

        $this->assertStringNotContainsString('DB_DATABASE', (string) file_get_contents($this->envDir.'/.env'));
        $this->assertFalse(session()->has('install.verified'));
    }

    public function test_connection_test_reports_success_and_failure()
    {
        $this->post(route('install.database.test'), $this->database())
            ->assertSessionHas('install.connection', ['ok' => true, 'message' => 'Connected to MySQL 8.0.36.']);

        $this->app->instance(DatabaseSetup::class, DatabaseSetupTest::fake(new PDOException('Access denied for user (using password: p@ss#word)')));

        $this->post(route('install.database.test'), $this->database())
            ->assertSessionHas('install.connection', fn (array $result) => $result['ok'] === false
                && str_contains($result['message'], 'Access denied')
                && ! str_contains($result['message'], 'p@ss#word'));
    }

    public function test_database_step_writes_env_and_unlocks_owner_step()
    {
        $this->post(route('install.database'), $this->database())
            ->assertRedirect(route('install.show'))
            ->assertSessionHasNoErrors();

        $env = Dotenv::parse((string) file_get_contents($this->envDir.'/.env'));
        $this->assertSame('mysql', $env['DB_CONNECTION']);
        $this->assertSame('shop', $env['DB_DATABASE']);
        $this->assertSame('shop_user', $env['DB_USERNAME']);
        $this->assertSame('p@ss#word', $env['DB_PASSWORD']);

        $this->get(route('install.show'))->assertInertia(fn (Assert $page) => $page
            ->where('step', 'owner')
            ->where('proof', 'session'));
    }

    public function test_proving_the_token_starts_a_new_session()
    {
        $this->get(route('install.show'));
        $before = session()->getId();

        // Send the session cookie, as a browser would, so the same session is resumed.
        $this->withCookie(config('session.cookie'), $before)
            ->post(route('install.database'), $this->database())
            ->assertSessionHasNoErrors();

        $this->assertNotSame($before, session()->getId());
        $this->assertTrue(session('install.verified'));
    }

    public function test_full_manual_flow_creates_the_owner()
    {
        $this->post(route('install.database'), $this->database());

        $this->post(route('install.store'), $this->ownerPayload())->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
        $this->assertTrue(User::query()->where('email', 'panel@example.com')->firstOrFail()->isOwner());
        $this->assertTrue(app(InstallationState::class)->isInstalled());
        $this->assertFileExists(app(InstallationState::class)->lockFile());
        $this->assertFileDoesNotExist($this->token->path());
        $this->assertFalse(session()->has('install.verified'));
    }

    public function test_owner_step_without_the_database_step_needs_proof()
    {
        $this->post(route('install.store'), $this->ownerPayload())->assertSessionHasErrors('setup_token');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_database_step_is_404_in_auto_mode()
    {
        config(['ecomodular.installer.mode' => 'detect']);

        $this->post(route('install.database'), $this->database())->assertNotFound();
        $this->post(route('install.database.test'), $this->database())->assertNotFound();
    }

    public function test_database_step_is_404_after_installation()
    {
        $this->installPlatform();

        $this->post(route('install.database'), $this->database())->assertNotFound();
    }
}
