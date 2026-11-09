<?php

namespace Tests\Feature\Install;

use App\Core\Installation\InstallationState;
use App\Core\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstallLockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('ECOMODULAR_OWNER_PASSWORD');
        parent::tearDown();
    }

    public function test_lock_file_alone_makes_the_installer_404()
    {
        file_put_contents(config('ecomodular.installer.lock_file'), now()->toIso8601String());

        $this->assertTrue(app(InstallationState::class)->isInstalled());
        $this->get('/install')->assertNotFound();
    }

    public function test_settings_row_alone_still_counts_as_installed()
    {
        app(SettingsRepository::class)->set(InstallationState::KEY, now()->toIso8601String());

        $this->assertTrue(app(InstallationState::class)->isInstalled());
        // Backfilled, so a later database outage cannot reopen the installer.
        $this->assertFileExists(config('ecomodular.installer.lock_file'));
    }

    public function test_mark_installed_writes_the_lock_file()
    {
        $state = app(InstallationState::class);
        $state->markInstalled();

        $this->assertFileExists($state->lockFile());
        $this->assertTrue($state->isInstalled());
    }

    public function test_missing_settings_table_means_not_installed()
    {
        Schema::drop('settings');

        $this->assertFalse(app(InstallationState::class)->isInstalled());
    }

    public function test_tests_never_touch_the_real_lock_file()
    {
        $this->assertNotSame(storage_path('app/installed'), config('ecomodular.installer.lock_file'));

        $this->installPlatform();

        $this->assertFileExists(config('ecomodular.installer.lock_file'));
    }

    public function test_cli_install_writes_the_lock_file()
    {
        putenv('ECOMODULAR_OWNER_PASSWORD=a-Very-strong-passphrase-42');

        $this->artisan('ecomodular:install', [
            '--store-name' => 'CLI Store',
            '--name' => 'CLI Owner',
            '--email' => 'cli@example.com',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertFileExists(config('ecomodular.installer.lock_file'));
    }
}
