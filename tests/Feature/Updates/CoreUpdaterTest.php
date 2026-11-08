<?php

namespace Tests\Feature\Updates;

use App\Core\Audit\AuditLog;
use App\Core\Marketplace\Client\UpdateHistory;
use App\Core\Operations\Backups;
use App\Core\Operations\HealthCheck;
use App\Core\Settings\SecretSettings;
use App\Core\Updates\CorePackage;
use App\Core\Updates\CoreUpdater;
use App\Core\Updates\CoreUpdateRun;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Core\Settings\SettingsRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CoreReleaseFixtures;
use Tests\TestCase;
use ZipArchive;

/**
 * The installer runs against a scratch app tree (`ecomodular.updates.root`);
 * artisan calls go through the `runArtisan` seam so migrations run in this
 * process against the scratch `database/migrations`.
 */
class CoreUpdaterTest extends TestCase
{
    // VACUUM INTO (database backups) cannot run inside RefreshDatabase's transaction.
    use CoreReleaseFixtures, DatabaseMigrations;

    /** @var list<string> */
    private array $calls = [];

    private ?bool $downDuringMigrate = null;

    /** Backups and code snapshots that existed when `down` ran. */
    private ?int $backupsAtDown = null;

    private ?int $snapshotsAtDown = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $this->scratchRoot();
        $this->useTestSigningKey();
    }

    private function queuedRun(array $item): CoreUpdateRun
    {
        return CoreUpdateRun::query()->forceCreate([
            'from_version' => '1.0.0',
            'to_version' => $item['version'],
            'release' => $item,
            'status' => CoreUpdateRun::QUEUED,
        ]);
    }

    private function updater(): CoreUpdater
    {
        $updater = app(CoreUpdater::class);
        $updater->runArtisan = function (string $root, array $args): int {
            $this->calls[] = $args[0];

            return match ($args[0]) {
                'down' => $this->down(),
                'up' => Artisan::call('up'),
                'migrate' => $this->migrate($root),
                default => 0,
            };
        };

        return $updater;
    }

    private function down(): int
    {
        $this->backupsAtDown = count(app(Backups::class)->all());
        $this->snapshotsAtDown = count(glob($this->root.'/storage/app/private/core-updates/core-*.zip') ?: []);

        return Artisan::call('down');
    }

    private function migrate(string $root): int
    {
        $this->downDuringMigrate = app()->isDownForMaintenance();

        return Artisan::call('migrate', ['--path' => $root.'/database/migrations', '--realpath' => true, '--force' => true]);
    }

    /** @return list<string> */
    private function oldPaths(): array
    {
        return array_merge(glob($this->root.'/*.old-*') ?: [], glob($this->root.'/*/*.old-*') ?: []);
    }

    private function assertUntouched(): void
    {
        $this->assertSame('1.0.0', $this->platformVersion());
        $this->assertSame('code 1.0.0', File::get($this->root.'/app/marker.txt'));
        $this->assertSame([], $this->oldPaths());
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertNotContains('down', $this->calls);
    }

    private function assertNoBackupTaken(): void
    {
        $this->assertSame([], app(Backups::class)->all());
        $this->assertSame([], glob($this->root.'/storage/app/private/core-updates/core-*.zip') ?: []);
    }

    public function test_installs_a_verified_release(): void
    {
        $this->connect();
        $zip = $this->releaseZip('1.1.0', $this->probeMigration());
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::COMPLETED, $result->status, (string) $result->message);
        $this->assertSame(CoreUpdateRun::COMPLETED, $run->fresh()->status);
        $this->assertSame('1.1.0', $this->platformVersion());
        $this->assertSame('code 1.1.0', File::get($this->root.'/app/marker.txt'));
        $this->assertStringContainsString('1.1.0', File::get($this->root.'/artisan'));
        $this->assertTrue(Schema::hasTable('core_probe'));
        $this->assertTrue($this->downDuringMigrate);
        $this->assertSame(['down', 'migrate', 'optimize:clear', 'up', 'queue:restart'], $this->calls);
        // Code snapshot before maintenance mode; database backup only once the site is down.
        $this->assertSame(1, $this->snapshotsAtDown);
        $this->assertSame(0, $this->backupsAtDown);
        $this->assertCount(1, app(Backups::class)->all());
        $this->assertFalse($run->fresh()->needs_attention);
        $this->assertFalse(app()->isDownForMaintenance());

        $history = UpdateHistory::query()->where('type', 'core')->sole();
        $this->assertTrue($history->success);
        $this->assertSame('modrixcommerce/core', $history->extension_name);
        $this->assertSame('update', $history->action);
        $this->assertSame('1.0.0', $history->from_version);
        $this->assertSame('1.1.0', $history->to_version);
        $this->assertSame(hash_file('sha256', $zip), $history->sha256);
        $this->assertTrue(AuditLog::query()->where('action', 'core.update_completed')->exists());

        $this->assertSame([], $this->oldPaths());
        $this->assertSame('APP_KEY=keep-me', File::get($this->root.'/.env'));
        $this->assertSame('storage-marker', File::get($this->root.'/storage/app/public/marker.txt'));
        $this->assertNotNull($run->fresh()->backup_name);
        $this->assertFileExists($this->root.'/'.$run->fresh()->code_backup_path);
        $this->assertSame([], File::directories($this->root.'/storage/app/private/core-updates/tmp'));
        $this->assertSame([], File::files($this->root.'/storage/app/private/core-updates/tmp'));
    }

    public function test_completion_forgets_the_installed_release(): void
    {
        $this->connect();
        $zip = $this->releaseZip('1.1.0');
        $this->fakeCoreCloud($zip);
        $item = $this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip));
        app(SettingsRepository::class)->set('core_update.available', ['version' => '1.1.0', 'item' => $item]);

        $this->assertSame(CoreUpdateRun::COMPLETED, $this->updater()->run($this->queuedRun($item))->status);

        $this->assertNull(app(SettingsRepository::class)->get('core_update.available'));
    }

    public function test_completion_keeps_a_newer_available_release(): void
    {
        $this->connect();
        $zip = $this->releaseZip('1.1.0');
        $this->fakeCoreCloud($zip);
        $item = $this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip));
        app(SettingsRepository::class)->set('core_update.available', ['version' => '1.2.0']);

        $this->assertSame(CoreUpdateRun::COMPLETED, $this->updater()->run($this->queuedRun($item))->status);

        $this->assertSame('1.2.0', app(SettingsRepository::class)->get('core_update.available')['version']);
    }

    public function test_a_sqlite_database_under_database_survives_and_is_never_shipped(): void
    {
        $this->connect();
        File::put($this->root.'/database/database.sqlite', 'live store data');
        File::put($this->root.'/database/database.sqlite-wal', 'live wal');
        $zip = $this->releaseZip('1.1.0', [
            'database/database.sqlite' => 'developer data',
            'database/seeders/DatabaseSeeder.php' => '<?php // 1.1.0',
            'database/factories/UserFactory.php' => '<?php // 1.1.0',
        ]);

        $archive = new ZipArchive;
        $archive->open($zip);
        $this->assertFalse($archive->locateName('database/database.sqlite'));
        $this->assertNotFalse($archive->locateName('database/seeders/DatabaseSeeder.php'));
        $archive->close();

        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::COMPLETED, $result->status, (string) $result->message);
        $this->assertSame('live store data', File::get($this->root.'/database/database.sqlite'));
        $this->assertSame('live wal', File::get($this->root.'/database/database.sqlite-wal'));
        $this->assertSame('<?php // 1.1.0', File::get($this->root.'/database/seeders/DatabaseSeeder.php'));
        $this->assertSame([], glob($this->root.'/database.old-*') ?: []);
        $this->assertSame([], glob($this->root.'/database/*.old-*') ?: []);
    }

    public function test_a_sqlite_database_inside_a_managed_path_refuses_the_install(): void
    {
        $this->connect();
        File::put($this->root.'/database/migrations/live.sqlite', 'live store data');
        config(['database.connections.sqlite.database' => $this->root.'/database/migrations/live.sqlite']);
        $zip = $this->releaseZip('1.1.0');
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::FAILED, $result->status);
        $this->assertStringContainsString('SQLite database is inside the managed path database/migrations', (string) $result->message);
        $this->assertSame('live store data', File::get($this->root.'/database/migrations/live.sqlite'));
        $this->assertUntouched();
        $this->assertNoBackupTaken();
    }

    public function test_a_failed_move_mid_swap_restores_the_earlier_paths(): void
    {
        $this->connect();
        // bootstrap/app.php is new in the package (2nd managed path); bootstrap/providers.php (3rd) cannot be moved aside.
        File::delete($this->root.'/bootstrap/app.php');
        $zip = $this->releaseZip('1.1.0', $this->probeMigration());
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));
        $updater = $this->updater();
        $blocked = $this->root.'/bootstrap/providers.php';
        $updater->mover = fn (string $from, string $to): bool => $from === $blocked && str_contains($to, '.old-') ? false : @rename($from, $to);

        $result = $updater->run($run);

        $this->assertSame(CoreUpdateRun::ROLLED_BACK, $result->status, (string) $result->message);
        $this->assertStringContainsString('Could not move bootstrap/providers.php aside', (string) $result->message);
        $this->assertSame('1.0.0', $this->platformVersion());
        $this->assertSame('code 1.0.0', File::get($this->root.'/app/marker.txt'));
        $this->assertFileDoesNotExist($this->root.'/bootstrap/app.php');
        $this->assertSame('<?php return [];', File::get($blocked));
        $this->assertSame([], $this->oldPaths());
        $this->assertSame([], glob($this->root.'/*.failed-*') ?: []);
        $this->assertNotContains('migrate', $this->calls);
        $this->assertFalse(Schema::hasTable('core_probe'));
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertFalse($run->fresh()->needs_attention);
    }

    public function test_a_rollback_that_cannot_put_code_back_needs_attention(): void
    {
        $this->connect();
        $zip = $this->releaseZip('1.1.0', $this->probeMigration(fails: true));
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));
        $updater = $this->updater();
        $app = $this->root.'/app';
        $updater->mover = fn (string $from, string $to): bool => $to === $app && str_contains($from, 'app.old-') ? false : @rename($from, $to);

        $result = $updater->run($run);

        $this->assertSame(CoreUpdateRun::FAILED, $result->status);
        $this->assertTrue($run->fresh()->needs_attention);
        $this->assertStringContainsString('could NOT be fully undone', (string) $result->message);
        $this->assertStringContainsString('could not put back app', (string) $result->message);
        $this->assertFalse(app()->isDownForMaintenance());

        $check = collect(app(HealthCheck::class)->run())->firstWhere('key', 'core_updates');
        $this->assertSame(HealthCheck::FAILED, $check['status']);
        $this->assertStringContainsString("Core update #{$run->id} failed and could not be fully undone", $check['detail']);
        $this->assertStringContainsString((string) $run->fresh()->code_backup_path, $check['detail']);
        $this->assertStringContainsString((string) $run->fresh()->backup_name, $check['detail']);
    }

    public function test_a_failed_artisan_up_falls_back_to_deleting_the_down_file(): void
    {
        $this->connect();
        $zip = $this->releaseZip('1.1.0');
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));
        $down = $this->root.'/storage/framework/down';
        $updater = app(CoreUpdater::class);
        $updater->runArtisan = function (string $root, array $args) use ($down): int {
            $this->calls[] = $args[0];
            if ($args[0] === 'down') {
                File::ensureDirectoryExists(dirname($down));
                File::put($down, '{}');
            }

            return $args[0] === 'up' ? 1 : 0;
        };

        $result = $updater->run($run);

        $this->assertSame(CoreUpdateRun::COMPLETED, $result->status, (string) $result->message);
        $this->assertFileDoesNotExist($down);
        $this->assertStringContainsString('php artisan up failed, so maintenance mode was lifted by deleting storage/framework/down', (string) $result->message);
        $this->assertStringNotContainsString('maintenance mode is still on', (string) $result->message);
    }

    public function test_a_busy_lock_does_not_fail_a_run_already_in_progress(): void
    {
        Http::fake();
        $run = $this->queuedRun($this->signedItem('1.1.0', str_repeat('ab', 32), 100));
        // Another process picked this very run up and holds the lock.
        CoreUpdateRun::query()->whereKey($run->id)->update(['status' => CoreUpdateRun::DOWNLOADING]);
        $lock = Cache::lock(CoreUpdater::LOCK, 60);
        $this->assertTrue($lock->get());

        try {
            $result = $this->updater()->run($run);
        } finally {
            $lock->release();
        }

        $this->assertSame(CoreUpdateRun::DOWNLOADING, $result->status);
        $this->assertSame(CoreUpdateRun::DOWNLOADING, $run->fresh()->status);
        $this->assertNull($run->fresh()->message);
        $this->assertSame(0, UpdateHistory::query()->count());
    }

    public function test_a_busy_lock_fails_a_run_that_is_still_queued(): void
    {
        Http::fake();
        $run = $this->queuedRun($this->signedItem('1.1.0', str_repeat('ab', 32), 100));
        $lock = Cache::lock(CoreUpdater::LOCK, 60);
        $this->assertTrue($lock->get());

        try {
            $result = $this->updater()->run($run);
        } finally {
            $lock->release();
        }

        $this->assertSame(CoreUpdateRun::FAILED, $result->status);
        $this->assertSame(CoreUpdateRun::FAILED, $run->fresh()->status);
        $this->assertStringContainsString('Another core update is running', (string) $run->fresh()->message);
    }

    public function test_checksum_mismatch_leaves_files_untouched(): void
    {
        $this->connect();
        $zip = $this->releaseZip('1.1.0');
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', str_repeat('ab', 32), filesize($zip)));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::FAILED, $result->status);
        $this->assertStringContainsString('checksum', strtolower((string) $result->message));
        $this->assertUntouched();
        $this->assertNoBackupTaken();
        $this->assertTrue(AuditLog::query()->where('action', 'core.update_failed')->exists());
        $this->assertFalse(UpdateHistory::query()->where('type', 'core')->sole()->success);
    }

    public function test_version_mismatch_inside_package_is_refused(): void
    {
        $this->connect();
        $zip = $this->releaseZip('1.2.0');
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::FAILED, $result->status);
        $this->assertStringContainsString('1.2.0', (string) $result->message);
        $this->assertUntouched();
        $this->assertNoBackupTaken();
    }

    public function test_unsafe_entry_is_refused_before_any_change(): void
    {
        $this->connect();
        $zip = $this->root.'-evil.zip';
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('app/Core/Platform.php', "<?php\nfinal class Platform { public const VERSION = '1.1.0'; }");
        $archive->addFromString('vendor/autoload.php', '<?php');
        $archive->addFromString('../x.php', '<?php // escaped');
        $archive->close();
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::FAILED, $result->status);
        $this->assertStringContainsString('unsafe', strtolower((string) $result->message));
        $this->assertFileDoesNotExist(dirname($this->root).'/x.php');
        $this->assertFileDoesNotExist($this->root.'/storage/app/private/core-updates/tmp/x.php');
        $this->assertSame([], File::allFiles($this->root.'/storage/app/private/core-updates/tmp'));
        $this->assertUntouched();
        $this->assertNoBackupTaken();
    }

    public function test_symlink_entry_is_refused(): void
    {
        $this->connect();
        $zip = $this->root.'-link.zip';
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('app/Core/Platform.php', "<?php\nfinal class Platform { public const VERSION = '1.1.0'; }");
        $archive->addFromString('vendor/autoload.php', '<?php');
        $archive->addFromString('app/link', '/etc/passwd');
        $archive->setExternalAttributesName('app/link', ZipArchive::OPSYS_UNIX, (0120777 << 16));
        $archive->close();
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::FAILED, $result->status);
        $this->assertUntouched();
    }

    public function test_failed_migration_rolls_back_code_and_database(): void
    {
        $this->connect();
        $kept = User::factory()->create(['email' => 'kept@example.test']);
        $zip = $this->releaseZip('1.1.0', $this->probeMigration(fails: true) + ['public/build/new-only.js' => 'x', 'lang/de/new.php' => '<?php']);
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::ROLLED_BACK, $result->status, (string) $result->message);
        $this->assertSame(CoreUpdateRun::ROLLED_BACK, $run->fresh()->status);
        $this->assertStringContainsString('Migration exploded', (string) $result->message);
        // Written after the restore, so the restored (older) row does not hide them.
        $this->assertNotNull($run->fresh()->backup_name);
        $this->assertNotNull($run->fresh()->code_backup_path);
        $this->assertSame('1.0.0', $this->platformVersion());
        $this->assertSame('code 1.0.0', File::get($this->root.'/app/marker.txt'));
        $this->assertFileDoesNotExist($this->root.'/public/build/new-only.js');
        $this->assertFileDoesNotExist($this->root.'/lang/de/new.php');
        $this->assertSame([], $this->oldPaths());
        $this->assertFalse(Schema::hasTable('core_probe'));
        $this->assertDatabaseHas('users', ['id' => $kept->id, 'email' => 'kept@example.test']);
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertContains('up', $this->calls);
        $this->assertTrue(AuditLog::query()->where('action', 'core.update_rolled_back')->exists());
        $this->assertFalse(UpdateHistory::query()->where('type', 'core')->sole()->success);
    }

    public function test_a_path_missing_from_the_package_is_left_alone(): void
    {
        $this->connect();
        $tree = $this->root.'-partial';
        $this->writeTree($tree, '1.1.0');
        File::deleteDirectory($tree.'/lang');
        $zip = (new CorePackage)->build($tree, $this->root.'-partial-out');
        $this->fakeCoreCloud($zip);
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::COMPLETED, $result->status, (string) $result->message);
        $this->assertFileExists($this->root.'/lang/en/x.php');
    }

    public function test_not_connected_fails_with_connect_message(): void
    {
        Http::fake();
        $zip = $this->releaseZip('1.1.0');
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::FAILED, $result->status);
        $this->assertStringContainsString('Connect', (string) $result->message);
        $this->assertUntouched();
        Http::assertNothingSent();
    }

    public function test_tampered_release_is_refused(): void
    {
        $this->connect();
        Http::fake();
        $run = $this->queuedRun($this->signedItem('1.1.0', str_repeat('ab', 32), 100, tamper: true));

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::FAILED, $result->status);
        $this->assertUntouched();
        Http::assertNothingSent();
    }

    public function test_only_queued_runs_are_started(): void
    {
        Http::fake();
        $run = $this->queuedRun($this->signedItem('1.1.0', str_repeat('ab', 32), 100));
        $run->forceFill(['status' => CoreUpdateRun::COMPLETED])->save();

        $result = $this->updater()->run($run);

        $this->assertSame(CoreUpdateRun::COMPLETED, $result->status);
        $this->assertSame(0, UpdateHistory::query()->count());
        Http::assertNothingSent();
    }

    public function test_run_is_refused_when_an_older_live_run_exists(): void
    {
        Http::fake();
        $item = $this->signedItem('1.1.0', str_repeat('ab', 32), 100);
        $first = $this->queuedRun($item);
        $second = $this->queuedRun($item);

        $result = $this->updater()->run($second);

        $this->assertSame(CoreUpdateRun::FAILED, $result->status);
        $this->assertSame('Another core update is already running.', $result->message);
        $this->assertSame(CoreUpdateRun::QUEUED, $first->fresh()->status);
        Http::assertNothingSent();
        $this->assertUntouched();
    }

    public function test_command_runs_a_queued_run(): void
    {
        Http::fake();
        $zip = $this->releaseZip('1.1.0');
        $run = $this->queuedRun($this->signedItem('1.1.0', hash_file('sha256', $zip), filesize($zip)));

        $this->artisan('ecomodular:core-update', ['run' => $run->id])->assertFailed();

        $this->assertSame(CoreUpdateRun::FAILED, $run->fresh()->status);
    }

    public function test_restore_database_puts_back_data_and_drops_later_tables(): void
    {
        $backups = app(Backups::class);
        $backup = $backups->create(withFiles: false);
        User::factory()->create(['email' => 'later@example.test']);
        Schema::create('later_table', fn ($t) => $t->id());

        $backups->restoreDatabase($backup['path']);

        $this->assertDatabaseMissing('users', ['email' => 'later@example.test']);
        $this->assertFalse(Schema::hasTable('later_table'));
        $this->assertTrue(Schema::hasTable('core_update_runs'));
    }

    /** The dump-replay path (MySQL/PostgreSQL): objects missing from the backup's listing are dropped. */
    public function test_objects_created_after_the_backup_are_dropped(): void
    {
        $backups = app(Backups::class);
        $manifest = $backups->verify($backups->create(withFiles: false)['path']);
        $this->assertContains('main.users', $manifest['database_tables']);
        $this->assertContains('main.migrations', $manifest['database_tables']);

        Schema::create('core_probe', fn ($t) => $t->id());
        Schema::create('core_probe_child', function ($t) {
            $t->id();
            $t->foreignId('probe_id')->constrained('core_probe');
        });
        DB::statement('create view later_view as select id from users');
        $kept = User::factory()->create();

        $dropped = $backups->dropObjectsNotIn($manifest);

        $this->assertEqualsCanonicalizing(['main.later_view', 'main.core_probe', 'main.core_probe_child'], $dropped);
        $this->assertFalse(Schema::hasTable('core_probe'));
        $this->assertFalse(Schema::hasTable('core_probe_child'));
        $this->assertSame([], Schema::getViews());
        $this->assertDatabaseHas('users', ['id' => $kept->id]);
        $this->assertSame([], $backups->dropObjectsNotIn($manifest));
    }

    public function test_old_manifests_without_a_listing_drop_nothing(): void
    {
        Schema::create('core_probe', fn ($t) => $t->id());

        $this->assertSame([], app(Backups::class)->dropObjectsNotIn(['format' => 1, 'files' => []]));
        $this->assertTrue(Schema::hasTable('core_probe'));
    }

    public function test_health_warns_about_a_run_that_stopped_progressing(): void
    {
        $run = CoreUpdateRun::query()->forceCreate(['from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::MIGRATING]);
        CoreUpdateRun::query()->whereKey($run->id)->update(['updated_at' => now()->subMinutes(CoreUpdateRun::STUCK_MINUTES + 2)]);

        $check = collect(app(HealthCheck::class)->run())->firstWhere('key', 'core_updates');

        $this->assertSame(HealthCheck::WARNING, $check['status']);
        $this->assertStringContainsString("Core update #{$run->id} has been migrating for", $check['detail']);
    }

    public function test_health_ignores_attention_runs_older_than_a_completed_one(): void
    {
        CoreUpdateRun::query()->forceCreate(['from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::FAILED, 'needs_attention' => true]);
        CoreUpdateRun::query()->forceCreate(['from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::COMPLETED]);

        $check = collect(app(HealthCheck::class)->run())->firstWhere('key', 'core_updates');

        $this->assertSame(HealthCheck::OK, $check['status']);
    }

    public function test_health_warns_when_the_last_run_was_rolled_back(): void
    {
        CoreUpdateRun::query()->forceCreate(['from_version' => '1.0.0', 'to_version' => '1.1.0', 'release' => [], 'status' => CoreUpdateRun::ROLLED_BACK]);

        $check = collect(app(HealthCheck::class)->run())->firstWhere('key', 'core_updates');

        $this->assertSame(HealthCheck::WARNING, $check['status']);
        $this->assertSame('The last core update was rolled back', $check['detail']);
    }
}
