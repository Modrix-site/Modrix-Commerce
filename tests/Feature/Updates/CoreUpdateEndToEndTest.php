<?php

namespace Tests\Feature\Updates;

use App\Core\Audit\AuditLog;
use App\Core\Marketplace\Client\UpdateHistory;
use App\Core\Updates\CoreUpdateCheck;
use App\Core\Updates\CoreUpdateLauncher;
use App\Core\Updates\CoreUpdater;
use App\Core\Updates\CoreUpdateRun;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CoreReleaseFixtures;
use Tests\TestCase;

/** Check, install and poll through the admin endpoints against a scratch app tree. */
class CoreUpdateEndToEndTest extends TestCase
{
    // VACUUM INTO (database backups) cannot run inside RefreshDatabase's transaction.
    use CoreReleaseFixtures, DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->scratchRoot();
        $this->useTestSigningKey();

        // The "detached process" runs synchronously, with migrations in this process.
        $this->app->instance(CoreUpdateLauncher::class, new class extends CoreUpdateLauncher
        {
            public function launch(CoreUpdateRun $run): void
            {
                $updater = app(CoreUpdater::class);
                $updater->runArtisan = fn (string $root, array $args): int => match ($args[0]) {
                    'down' => Artisan::call('down'),
                    'up' => Artisan::call('up'),
                    'migrate' => Artisan::call('migrate', ['--path' => $root.'/database/migrations', '--realpath' => true, '--force' => true]),
                    default => 0,
                };
                $updater->run($run);
            }
        });
    }

    public function test_check_then_install_from_admin(): void
    {
        $this->connect();
        $zip = $this->releaseZip('1.1.0', $this->probeMigration());
        $item = $this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip));
        $this->fakeCoreCloud($zip, [$item]);
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.system.core-update.check'))->assertRedirect();
        $this->assertSame('1.1.0', app(CoreUpdateCheck::class)->available()['version']);

        $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.system.core-update.start'))
            ->assertRedirect()->assertSessionHasNoErrors();

        $run = CoreUpdateRun::query()->sole();
        $this->actingAs($owner)->getJson(route('admin.system.core-update.show', $run))
            ->assertOk()->assertJson(['status' => 'completed', 'to_version' => '1.1.0']);

        $this->assertSame('1.1.0', $this->platformVersion());
        $this->assertTrue(Schema::hasTable('core_probe'));
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertTrue(UpdateHistory::query()->where('type', 'core')->sole()->success);
        $this->assertNull(app(CoreUpdateCheck::class)->available(), 'the installed release is no longer offered');
        foreach (['core.update_started', 'core.update_completed'] as $action) {
            $this->assertTrue(AuditLog::query()->where('action', $action)->exists(), $action);
        }
    }
}
