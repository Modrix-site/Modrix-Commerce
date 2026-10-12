<?php

namespace Tests\Feature\Install;

use App\Core\Installation\InstallationState;
use App\Core\Installation\InstallerToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebInstallerTest extends TestCase
{
    use RefreshDatabase;

    private InstallerToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = app(InstallerToken::class);
        $this->token->delete();
    }

    protected function tearDown(): void
    {
        $this->token->delete();
        parent::tearDown();
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'setup_token' => $this->token->ensure(),
            'store_name' => 'Test Store',
            'default_currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
            'name' => 'Store Owner',
            'email' => 'Owner@Example.com',
            'password' => 'a-Very-strong-passphrase-42',
            'password_confirmation' => 'a-Very-strong-passphrase-42',
        ], $overrides);
    }

    public function test_installer_page_renders_without_revealing_the_token()
    {
        $response = $this->get(route('install.show'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('install/index')
            ->has('checks')
            ->where('tokenLocation', 'storage/app/private/installer.token'));

        $this->assertFileExists($this->token->path());
        $this->assertStringNotContainsString($this->token->ensure(), $response->getContent());
    }

    public function test_installation_requires_the_setup_token()
    {
        $this->post(route('install.store'), $this->payload(['setup_token' => 'wrong-token']))
            ->assertSessionHasErrors('setup_token');

        $this->assertFalse(app(InstallationState::class)->isInstalled());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_successful_installation_creates_owner_and_locks_installer()
    {
        $response = $this->post(route('install.store'), $this->payload());

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();

        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($owner->isOwner());
        $this->assertNotNull($owner->email_verified_at);
        $this->assertTrue(app(InstallationState::class)->isInstalled());
        $this->assertFileDoesNotExist($this->token->path());
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.installed', 'actor_id' => $owner->id]);
        $this->assertDatabaseHas('roles', ['name' => 'administrator', 'is_system' => true]);

        // The installer no longer exists.
        $this->get(route('install.show'))->assertNotFound();
        $this->post(route('install.store'), $this->payload(['email' => 'attacker@example.com']))->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_owner_password_must_be_confirmed()
    {
        $this->post(route('install.store'), $this->payload(['password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');

        $this->assertFalse(app(InstallationState::class)->isInstalled());
    }

    public function test_admin_redirects_to_installer_before_installation()
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('install.show'));
    }
}
