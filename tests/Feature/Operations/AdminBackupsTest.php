<?php

namespace Tests\Feature\Operations;

use App\Core\Operations\BackupOperation;
use App\Core\Operations\Backups;
use App\Core\Operations\OperationInProgress;
use App\Core\Platform;
use App\Core\Settings\BackupSettings;
use App\Domains\Catalog\Models\Product;
use App\Jobs\CreateBackupJob;
use App\Jobs\RestoreBackupJob;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;
use ZipArchive;

class AdminBackupsTest extends TestCase
{
    use CommerceFixtures, DatabaseMigrations;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        // A scratch tree stands in for storage/app, modules/ and themes/.
        $this->root = storage_path('framework/testing/backup-'.Str::random(8));
        foreach (['storage/app/public/products', 'storage/app/private/backups', 'modules/acme/hello', 'themes/acme/sun'] as $dir) {
            File::ensureDirectoryExists($this->root.'/'.$dir);
        }
        File::put($this->root.'/storage/app/public/products/a.webp', 'image-bytes');
        File::put($this->root.'/modules/acme/hello/module.json', '{"name":"acme/hello"}');
        File::put($this->root.'/themes/acme/sun/theme.json', '{"name":"acme/sun"}');
        config([
            'ecomodular.backup.files_root' => $this->root,
            'ecomodular.backup.path' => $this->root.'/storage/app/private/backups',
            'ecomodular.backup.password' => '',
            // Never put the real (shared) storage/framework/down in place.
            'app.maintenance.driver' => 'cache',
            'app.maintenance.store' => 'array',
        ]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->root));
    }

    public function test_backup_settings_fall_back_to_config()
    {
        config(['ecomodular.backup.schedule' => '03:15', 'ecomodular.backup.keep' => 4]);
        $settings = app(BackupSettings::class);

        $this->assertSame('03:15', $settings->schedule());
        $this->assertSame(4, $settings->keep());
        $this->assertTrue($settings->withFiles());
    }

    public function test_saved_backup_settings_win_and_drive_next_run()
    {
        $settings = app(BackupSettings::class);
        $settings->save(['schedule' => '04:00', 'with_files' => false, 'keep' => 2]);

        $this->assertSame('04:00', $settings->schedule());
        $this->assertFalse($settings->withFiles());
        $this->assertSame(2, $settings->keep());

        Carbon::setTestNow('2026-11-09 05:00:00');
        $this->assertSame('2026-11-10 04:00', $settings->nextRun()?->format('Y-m-d H:i'));
        Carbon::setTestNow('2026-11-09 03:00:00');
        $this->assertSame('2026-11-09 04:00', $settings->nextRun()?->format('Y-m-d H:i'));

        $settings->save(['schedule' => '', 'with_files' => true, 'keep' => 2]);
        $this->assertNull($settings->nextRun());
        Carbon::setTestNow();
    }

    public function test_prune_uses_saved_keep()
    {
        app(BackupSettings::class)->save(['schedule' => '02:30', 'with_files' => false, 'keep' => 2]);
        $backups = app(Backups::class);

        $backups->create(false);
        $backups->create(false);
        $backups->create(false);

        $this->assertCount(2, $backups->all());
    }

    public function test_administrator_role_lacks_backups_manage()
    {
        $this->assertFalse($this->userWithRole('administrator')->can('backups.manage'));
        $this->assertTrue($this->owner()->can('backups.manage'));
    }

    public function test_describe_reports_files_and_encryption()
    {
        $backups = app(Backups::class);
        $plain = $backups->create(true);
        $this->assertSame(['encrypted' => false, 'readable' => true, 'includes_files' => true, 'platform_version' => Platform::VERSION], $backups->describe($plain['path']));

        config(['ecomodular.backup.password' => 'p1']);
        $secret = $backups->create(false);
        $this->assertSame(['encrypted' => true, 'readable' => true, 'includes_files' => false], array_intersect_key($backups->describe($secret['path']), array_flip(['encrypted', 'readable', 'includes_files'])));

        config(['ecomodular.backup.password' => 'p2']);
        $mismatch = $backups->describe($secret['path']);
        $this->assertTrue($mismatch['encrypted']);
        $this->assertFalse($mismatch['readable']);
        $this->assertNull($mismatch['includes_files']);
    }

    public function test_describe_survives_a_corrupt_archive()
    {
        $path = app(Backups::class)->directory().'/ecomodular-backup-20260101-000000-abcd.zip';
        File::put($path, 'junk');

        $this->assertFalse(app(Backups::class)->describe($path)['readable']);
    }

    public function test_operation_blocks_while_running_but_not_when_stale()
    {
        $operation = app(BackupOperation::class);
        $first = $operation->begin('backup', null, false, 1);
        $this->assertSame('queued', $operation->current()['state']);
        $this->assertTrue($operation->busy());

        try {
            $operation->begin('backup', null, false, 1);
            $this->fail('A second operation must be refused.');
        } catch (OperationInProgress) {
        }

        $operation->update($first['id'], ['state' => 'running']);
        $this->assertSame('running', $operation->current()['state']);
        $operation->update('other-id', ['state' => 'done']);
        $this->assertSame('running', $operation->current()['state']);

        $file = app(Backups::class)->directory().'/.operation.json';
        $status = json_decode(File::get($file), true);
        $status['queued_at'] = now()->subSeconds((int) config('ecomodular.backup.timeout') + 1)->toIso8601String();
        File::put($file, json_encode($status));

        $this->assertSame('failed', $operation->current()['state']);
        $this->assertFalse($operation->busy());
        $this->assertSame('queued', $operation->begin('backup', null, true, 1)['state']);
    }

    public function test_backup_job_creates_an_archive_and_reports_done()
    {
        $owner = $this->owner();
        $status = app(BackupOperation::class)->begin('backup', null, false, $owner->id);

        app()->call([new CreateBackupJob(false, $status['id'], $owner->id), 'handle']);

        $backups = app(Backups::class)->all();
        $this->assertCount(1, $backups);
        $current = app(BackupOperation::class)->current();
        $this->assertSame('done', $current['state']);
        $this->assertSame($backups[0]['name'], $current['backup']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.backup_created']);
    }

    public function test_backup_job_failure_is_reported()
    {
        $owner = $this->owner();
        $status = app(BackupOperation::class)->begin('backup', null, false, $owner->id);

        // SQLite cannot VACUUM INTO inside a transaction: the dump really fails.
        DB::beginTransaction();
        try {
            app()->call([new CreateBackupJob(false, $status['id'], $owner->id), 'handle']);
        } finally {
            DB::rollBack();
        }

        $current = app(BackupOperation::class)->current();
        $this->assertSame('failed', $current['state']);
        $this->assertNotEmpty($current['message']);
        $this->assertSame([], app(Backups::class)->all());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'system.backup_created']);
    }

    public function test_restore_job_round_trip()
    {
        $owner = $this->owner();
        $product = Product::factory()->create(['name' => 'Before']);
        $backup = app(Backups::class)->create(false);
        $product->forceFill(['name' => 'After'])->save();
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
        $status = app(BackupOperation::class)->begin('restore', $backup['name'], false, $owner->id);

        app()->call([new RestoreBackupJob($backup['name'], $status['id'], $owner->id), 'handle']);

        $this->assertSame('Before', Product::query()->find($product->id)->name);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertFalse(app()->isDownForMaintenance());
        $current = app(BackupOperation::class)->current();
        $this->assertSame('done', $current['state'], (string) $current['message']);
        $this->assertNotNull($current['pre_restore']);
        $this->assertFileExists(app(Backups::class)->directory().'/'.$current['pre_restore']);
        $this->assertFileExists($backup['path']);
    }

    public function test_restore_job_keeps_the_restored_archive_even_at_the_keep_limit()
    {
        app(BackupSettings::class)->save(['schedule' => '', 'with_files' => false, 'keep' => 1]);
        $owner = $this->owner();
        $backup = app(Backups::class)->create(false);
        $status = app(BackupOperation::class)->begin('restore', $backup['name'], false, $owner->id);

        app()->call([new RestoreBackupJob($backup['name'], $status['id'], $owner->id), 'handle']);

        $this->assertSame('done', app(BackupOperation::class)->current()['state']);
    }

    public function test_restore_job_failure_after_import_started_leaves_store_down()
    {
        $owner = $this->owner();
        $backup = app(Backups::class)->create(false);
        // Damage the dump: the manifest checksum no longer matches.
        $zip = new ZipArchive;
        $zip->open($backup['path']);
        $zip->addFromString('database/database.sqlite', 'tampered');
        $zip->close();
        $status = app(BackupOperation::class)->begin('restore', $backup['name'], false, $owner->id);

        app()->call([new RestoreBackupJob($backup['name'], $status['id'], $owner->id), 'handle']);

        $this->assertTrue(app()->isDownForMaintenance());
        $current = app(BackupOperation::class)->current();
        $this->assertSame('failed', $current['state']);
        $this->assertStringContainsString('--force', $current['recovery'][0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.backup_restore_failed']);
        Artisan::call('up');
    }

    public function test_routes_require_backups_manage()
    {
        $this->get('/admin/settings/backups')->assertRedirect();

        $this->roleWithPermissions('ops', ['settings.manage']);
        $user = $this->userWithRole('ops');
        $this->actingAs($user)->get('/admin/settings/backups')->assertForbidden();
        $this->actingAs($user)->post('/admin/settings/backups', ['with_files' => false])->assertForbidden();
        $this->actingAs($user)->get('/admin/settings/backups/status')->assertForbidden();
    }

    public function test_index_lists_backups_and_schedule()
    {
        app(Backups::class)->create(false);
        app(Backups::class)->create(true);

        $owner = $this->owner();
        $this->actingAs($owner)->get('/admin/settings/backups')->assertRedirect(route('password.confirm'));

        $this->actingAsConfirmed($owner)
            ->get('/admin/settings/backups')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settings/backups')
                ->has('backups', 2)
                ->has('backups.0', fn (Assert $row) => $row->hasAll(['name', 'size', 'created_at', 'encrypted', 'readable', 'includes_files', 'platform_version'])->missing('path'))
                ->where('settings.keep', 7)
                ->has('env_defaults')
                ->whereNot('next_run', null)
                ->has('scheduler_alive')
                ->where('encryption_configured', false)
                ->where('operation', null));
    }

    public function test_store_queues_a_backup_and_refuses_a_second()
    {
        Queue::fake();
        $owner = $this->owner();

        $this->actingAs($owner)->post('/admin/settings/backups', ['with_files' => false])->assertRedirect();
        Queue::assertPushed(CreateBackupJob::class, fn (CreateBackupJob $job) => $job->withFiles === false && $job->userId === $owner->id);

        $this->actingAs($owner)->post('/admin/settings/backups', ['with_files' => true])
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'error');
        Queue::assertPushed(CreateBackupJob::class, 1);

        $this->actingAs($owner)->getJson('/admin/settings/backups/status')->assertOk()->assertJsonPath('operation.state', 'queued');
    }

    public function test_download_and_delete()
    {
        $owner = $this->owner();
        $backup = app(Backups::class)->create(false);

        $this->actingAs($owner)->get("/admin/settings/backups/{$backup['name']}/download")->assertRedirect(route('password.confirm'));

        $response = $this->actingAsConfirmed($owner)->get("/admin/settings/backups/{$backup['name']}/download");
        $response->assertOk();
        $this->assertStringContainsString($backup['name'], (string) $response->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.backup_downloaded']);

        $this->actingAsConfirmed($owner)->delete("/admin/settings/backups/{$backup['name']}")->assertRedirect();
        $this->assertFileDoesNotExist($backup['path']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.backup_deleted']);
    }

    public function test_invalid_names_are_404()
    {
        $owner = $this->owner();
        File::put($this->root.'/storage/app/private/foo.zip', 'x');

        foreach (['foo.zip', 'ecomodular-backup-20260101-000000-zzzz.zip', '..%2Ffoo.zip', '..%5Cfoo.zip', '.operation.json'] as $name) {
            $this->actingAsConfirmed($owner)->get("/admin/settings/backups/{$name}/download")->assertNotFound();
            $this->actingAsConfirmed($owner)->delete("/admin/settings/backups/{$name}")->assertNotFound();
        }
        $this->assertFileExists($this->root.'/storage/app/private/foo.zip');
    }

    public function test_schedule_validation_and_save()
    {
        $owner = $this->owner();

        foreach (['24:00', '7:5', 'abc'] as $bad) {
            $this->actingAs($owner)->put('/admin/settings/backups/schedule', ['schedule' => $bad, 'with_files' => true, 'keep' => 7])->assertSessionHasErrors('schedule');
        }
        foreach ([0, 101] as $bad) {
            $this->actingAs($owner)->put('/admin/settings/backups/schedule', ['schedule' => '02:30', 'with_files' => true, 'keep' => $bad])->assertSessionHasErrors('keep');
        }

        $this->actingAs($owner)->put('/admin/settings/backups/schedule', ['schedule' => '04:30', 'with_files' => false, 'keep' => 3])->assertSessionHasNoErrors();
        $settings = app(BackupSettings::class);
        $this->assertSame(['04:30', false, 3], [$settings->schedule(), $settings->withFiles(), $settings->keep()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.backups_updated']);

        $this->actingAs($owner)->put('/admin/settings/backups/schedule', ['schedule' => null, 'with_files' => true, 'keep' => 3])->assertSessionHasNoErrors();
        $this->assertSame('', app(BackupSettings::class)->schedule());
    }

    public function test_restore_guards()
    {
        Queue::fake();
        $owner = $this->owner();
        $backup = app(Backups::class)->create(false);
        $url = "/admin/settings/backups/{$backup['name']}/restore";

        $this->actingAs($owner)->post($url, ['confirm_name' => $backup['name']])->assertRedirect(route('password.confirm'));
        $this->actingAsConfirmed($owner)->post($url, ['confirm_name' => 'nope'])->assertSessionHasErrors('confirm_name');

        config(['ecomodular.backup.password' => 'other']);
        $encrypted = app(Backups::class)->create(false);
        config(['ecomodular.backup.password' => 'changed']);
        $this->actingAsConfirmed($owner)->post("/admin/settings/backups/{$encrypted['name']}/restore", ['confirm_name' => $encrypted['name']])->assertSessionHasErrors('backup');
        config(['ecomodular.backup.password' => '']);

        $corrupt = 'ecomodular-backup-20200101-000000-abcd.zip';
        File::put(app(Backups::class)->directory().'/'.$corrupt, 'junk');
        $this->actingAsConfirmed($owner)->post("/admin/settings/backups/{$corrupt}/restore", ['confirm_name' => $corrupt])->assertSessionHasErrors('backup');
        Queue::assertNothingPushed();

        $this->actingAsConfirmed($owner)->post($url, ['confirm_name' => $backup['name']])->assertSessionHasNoErrors()->assertRedirect();
        Queue::assertPushed(RestoreBackupJob::class, fn (RestoreBackupJob $job) => $job->name === $backup['name'] && strlen((string) $job->secret) === 32);
        $this->actingAs($owner)->getJson('/admin/settings/backups/status')->assertJsonPath('operation.type', 'restore')->assertJsonPath('operation.secret', fn ($s) => is_string($s) && strlen($s) === 32);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.backup_restore_started']);
    }

    public function test_owner_navigation_includes_backups()
    {
        $keys = [];
        $this->actingAs($this->owner())
            ->get(route('admin.dashboard'))
            ->assertInertia(function (Assert $page) use (&$keys) {
                $keys = collect($page->toArray()['props']['adminNavigation'])->flatMap(fn ($g) => collect($g['items'])->pluck('key'))->all();
            });

        $this->assertContains('backups', $keys);
    }
}
