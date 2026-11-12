<?php

namespace Tests\Feature\Operations;

use App\Core\Operations\Backups;
use App\Core\Operations\HealthCheck;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;
use ZipArchive;

class HealthAndBackupTest extends TestCase
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
        ]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->root));
    }

    public function test_health_endpoint_shows_details_only_with_the_token()
    {
        // The heartbeat lives in the real storage directory: put back whatever was there.
        $file = storage_path(HealthCheck::HEARTBEAT_FILE);
        $previous = is_file($file) ? File::get($file) : null;
        $this->beforeApplicationDestroyed(fn () => $previous === null ? File::delete($file) : File::put($file, $previous));
        HealthCheck::beat();
        config(['ecomodular.health.token' => 'secret-health-token']);

        $this->getJson('/health')->assertOk()->assertJsonMissingPath('checks')->assertJsonStructure(['status']);
        $this->getJson('/health', ['Authorization' => 'Bearer wrong'])->assertJsonMissingPath('checks');
        $this->getJson('/health', ['Authorization' => 'Bearer secret-health-token'])
            ->assertOk()
            ->assertJsonPath('checks.0.key', 'database')
            ->assertJsonFragment(['key' => 'scheduler', 'status' => 'ok']);
    }

    public function test_a_failed_check_returns_503_and_a_failing_exit_code()
    {
        // Any disk is below a 101% free threshold.
        config(['ecomodular.health.disk_fail_percent' => 101]);

        $this->getJson('/health')->assertStatus(503)->assertJsonPath('status', 'failed');
        $this->artisan('ecomodular:health')->assertExitCode(1);
    }

    public function test_production_configuration_is_checked()
    {
        $this->app->detectEnvironment(fn () => 'production');
        $check = fn () => collect(app(HealthCheck::class)->run())->firstWhere('key', 'configuration');

        try {
            config(['app.debug' => true]);
            $this->assertSame('failed', $check()['status']);

            config(['app.debug' => false, 'app.url' => 'http://shop.test', 'session.secure' => false]);
            $this->assertSame('warning', $check()['status']);
            $this->assertStringContainsString('APP_URL is not https', $check()['detail']);

            config(['app.url' => 'https://shop.test', 'session.secure' => true, 'mail.default' => 'smtp']);
            $this->assertSame('ok', $check()['status']);
        } finally {
            // Teardown migrations would ask for confirmation in production.
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_status_page_lists_health_checks()
    {
        $this->actingAs($this->owner())->get(route('admin.system.status'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('health', 12)->where('health.0.key', 'database'));
    }

    public function test_backup_and_restore_round_trip()
    {
        $product = $this->sellable(product: ['name' => 'Kept Lamp'])->product;
        $backups = app(Backups::class);

        $this->artisan('ecomodular:backup')->assertSuccessful();
        $backup = $backups->latest();
        $this->assertNotNull($backup);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.backup_created']);

        // Things go wrong after the backup.
        $product->forceFill(['name' => 'Renamed Lamp'])->save();
        Product::factory()->create(['name' => 'Added Later']);
        File::put($this->root.'/storage/app/public/products/a.webp', 'corrupted');
        File::deleteDirectory($this->root.'/modules/acme/hello');

        $backups->restore($backup['path']);

        $this->assertDatabaseHas('products', ['name' => 'Kept Lamp']);
        $this->assertDatabaseMissing('products', ['name' => 'Added Later']);
        $this->assertSame('image-bytes', File::get($this->root.'/storage/app/public/products/a.webp'));
        $this->assertFileExists($this->root.'/modules/acme/hello/module.json');
        // The backup directory itself is never replaced.
        $this->assertFileExists($backup['path']);
        $this->assertSame([], glob($this->root.'/*/*.restore-*') ?: []);
    }

    public function test_encrypted_backups_need_the_password()
    {
        config(['ecomodular.backup.password' => 'correct horse battery']);
        $backups = app(Backups::class);
        $backup = $backups->create();
        $this->assertTrue($backup['encrypted']);

        $zip = new ZipArchive;
        $zip->open($backup['path']);
        $this->assertSame(ZipArchive::EM_AES_256, $zip->statName('manifest.json')['encryption_method']);
        $zip->close();

        $backups->verify($backup['path']);

        config(['ecomodular.backup.password' => 'wrong']);
        $this->expectException(RuntimeException::class);
        $backups->verify($backup['path']);
    }

    public function test_a_modified_archive_is_refused_before_anything_changes()
    {
        $backups = app(Backups::class);
        $backup = $backups->create();

        $zip = new ZipArchive;
        $zip->open($backup['path']);
        $zip->addFromString('files/storage-public/products/a.webp', 'tampered');
        $zip->close();

        $this->sellable(product: ['name' => 'Still Here']);

        try {
            $backups->restore($backup['path']);
            $this->fail('A modified archive was restored.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Checksum mismatch', $e->getMessage());
        }

        $this->assertDatabaseHas('products', ['name' => 'Still Here']);
        $this->artisan('ecomodular:restore', ['backup' => $backup['name'], '--verify-only' => true])->assertExitCode(1);
    }

    public function test_unsafe_archive_paths_are_refused()
    {
        $path = $this->root.'/storage/app/private/backups/ecomodular-backup-evil.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('../../evil.php', '<?php');
        $zip->addFromString('manifest.json', (string) json_encode(['format' => 1, 'files' => ['../../evil.php' => hash('sha256', '<?php')]]));
        $zip->close();

        $this->expectExceptionMessage('unsafe path');
        app(Backups::class)->verify($path);
    }

    public function test_retention_keeps_the_newest_backups()
    {
        config(['ecomodular.backup.keep' => 2]);
        $backups = app(Backups::class);

        foreach ([1, 2, 3] as $i) {
            $made = $backups->create(withFiles: false);
            touch($made['path'], time() - (10 - $i) * 60);
        }
        $backups->create(withFiles: false);

        $this->assertCount(2, $backups->all());
    }

    public function test_restore_requires_confirmation()
    {
        $backup = app(Backups::class)->create(withFiles: false);

        $this->artisan('ecomodular:restore', ['backup' => $backup['name']])
            ->expectsQuestion('Type "restore" to continue', 'no')
            ->assertExitCode(1);
    }
}
