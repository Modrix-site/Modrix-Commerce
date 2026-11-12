<?php

namespace Tests\Feature\Install;

use App\Core\Installation\InstallationState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('ECOMODULAR_OWNER_PASSWORD');
        parent::tearDown();
    }

    public function test_non_interactive_install_creates_owner()
    {
        putenv('ECOMODULAR_OWNER_PASSWORD=a-Very-strong-passphrase-42');

        $this->artisan('ecomodular:install', [
            '--store-name' => 'CLI Store',
            '--name' => 'CLI Owner',
            '--email' => 'cli@example.com',
            '--currency' => 'GBP',
            '--timezone' => 'Europe/London',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'cli@example.com')->firstOrFail()->isOwner());
        $this->assertTrue(app(InstallationState::class)->isInstalled());
    }

    public function test_install_rejects_weak_password()
    {
        putenv('ECOMODULAR_OWNER_PASSWORD=short');

        $this->artisan('ecomodular:install', [
            '--store-name' => 'CLI Store',
            '--name' => 'CLI Owner',
            '--email' => 'cli@example.com',
            '--no-interaction' => true,
        ])->assertFailed();

        $this->assertFalse(app(InstallationState::class)->isInstalled());
    }

    public function test_install_cannot_be_rerun()
    {
        $this->installPlatform();

        $this->artisan('ecomodular:install', ['--no-interaction' => true])->assertFailed();
    }
}
