<?php

namespace Tests\Feature\Install;

use App\Core\Installation\InstallationState;
use App\Core\Installation\InstallerToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Git deploys (Uplary and similar): the database is configured and migrated
 * by the deploy, so the installer only creates the owner, proven by the
 * configured database password.
 */
class AutoDeployInstallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(InstallerToken::class)->delete();
    }

    protected function tearDown(): void
    {
        app(InstallerToken::class)->delete();
        parent::tearDown();
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'Deploy Store',
            'default_currency' => 'USD',
            'timezone' => 'UTC',
            'name' => 'Deploy Owner',
            'email' => 'deploy@example.com',
            'password' => 'a-Very-strong-passphrase-42',
            'password_confirmation' => 'a-Very-strong-passphrase-42',
        ], $overrides);
    }

    public function test_auto_mode_shows_only_the_owner_step()
    {
        config(['database.connections.sqlite.password' => 'deploy-pass']);

        $this->get(route('install.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('install/index')
            ->where('mode', 'auto')
            ->where('step', 'owner')
            ->where('proof', 'database_password')
            ->where('databaseProblem', null));
    }

    public function test_the_page_never_contains_the_database_password()
    {
        config(['database.connections.sqlite.password' => 'deploy-pass-9f2c']);

        $this->assertStringNotContainsString('deploy-pass-9f2c', $this->get(route('install.show'))->getContent());
    }

    public function test_wrong_database_password_is_rejected()
    {
        config(['database.connections.sqlite.password' => 'deploy-pass']);

        $this->post(route('install.store'), $this->payload(['database_password' => 'nope']))
            ->assertSessionHasErrors(['database_password' => 'The database password is incorrect.']);

        $this->assertDatabaseCount('users', 0);
        $this->assertFalse(app(InstallationState::class)->isInstalled());
    }

    public function test_empty_database_password_requires_the_setup_token()
    {
        $this->get(route('install.show'))->assertInertia(fn (Assert $page) => $page->where('proof', 'setup_token'));

        $this->post(route('install.store'), $this->payload(['database_password' => '']))
            ->assertSessionHasErrors('setup_token');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_correct_database_password_installs_and_locks()
    {
        config(['database.connections.sqlite.password' => 'deploy-pass']);

        $this->post(route('install.store'), $this->payload(['database_password' => 'deploy-pass']))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
        $this->assertTrue(User::query()->where('email', 'deploy@example.com')->firstOrFail()->isOwner());
        $this->assertFileExists(app(InstallationState::class)->lockFile());

        $this->get(route('install.show'))->assertNotFound();
        $this->post(route('install.store'), $this->payload(['database_password' => 'deploy-pass', 'email' => 'second@example.com']))
            ->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_the_owner_stays_signed_in_when_sessions_live_in_the_database()
    {
        // Before install sessions are kept in files; the owner's session must move to the
        // configured database driver, or the redirect to the admin lands on the login page.
        config(['session.driver' => 'database', 'database.connections.sqlite.password' => 'deploy-pass']);

        $this->post(route('install.store'), $this->payload(['database_password' => 'deploy-pass']))
            ->assertRedirect(route('admin.dashboard'));

        $owner = User::query()->where('email', 'deploy@example.com')->firstOrFail();
        $this->assertDatabaseHas('sessions', ['user_id' => $owner->id]);
    }

    public function test_forced_auto_with_pending_migrations_explains_the_problem()
    {
        config(['ecomodular.installer.mode' => 'auto']);
        DB::table('migrations')->delete();

        $this->get(route('install.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'auto')
            ->where('step', 'requirements')
            ->where('databaseProblem', 'not_migrated'));

        $this->post(route('install.database'), [])->assertNotFound();
    }
}
