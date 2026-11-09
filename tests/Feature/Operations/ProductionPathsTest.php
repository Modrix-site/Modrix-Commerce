<?php

namespace Tests\Feature\Operations;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Release-candidate drills in separate processes against a throwaway
 * database and file tree, exactly as an operator runs them:
 * fresh install, upgrade from an older schema, and backup → damage →
 * restore. Nothing here touches the developer's database or files.
 */
class ProductionPathsTest extends TestCase
{
    private string $dir;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/drill-'.Str::random(8));
        File::ensureDirectoryExists($this->dir.'/root/storage/app/public/products');
        File::ensureDirectoryExists($this->dir.'/root/storage/app/private');
        File::ensureDirectoryExists($this->dir.'/backups');
        File::copyDirectory(base_path('modules'), $this->dir.'/root/modules');
        File::copyDirectory(base_path('themes'), $this->dir.'/root/themes');
        $this->database = $this->dir.'/store.sqlite';
        File::put($this->database, '');
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->dir));
    }

    /**
     * @param  list<string>  $arguments
     */
    private function console(array $arguments, bool $mustSucceed = true): string
    {
        $result = Process::path(base_path())
            ->env([
                'APP_ENV' => 'local',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->database,
                'DB_URL' => '',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'MAIL_MAILER' => 'array',
                // Maintenance mode for the restore stays inside this process.
                'APP_MAINTENANCE_DRIVER' => 'cache',
                'APP_MAINTENANCE_STORE' => 'array',
                'ECOMODULAR_BACKUP_PATH' => $this->dir.'/backups',
                'ECOMODULAR_BACKUP_FILES_ROOT' => $this->dir.'/root',
                'ECOMODULAR_BACKUP_PASSWORD' => 'drill-password-123',
                'ECOMODULAR_OWNER_PASSWORD' => 'Drill-Owner-Password-1!',
                // The drill store's own install lock, not the checkout's storage/app/installed.
                'ECOMODULAR_INSTALL_LOCK' => $this->dir.'/installed',
            ])
            ->timeout(180)
            ->run([PHP_BINARY, 'artisan', ...$arguments]);

        if ($mustSucceed) {
            $this->assertTrue($result->successful(), implode(' ', $arguments)."\n".$result->errorOutput().$result->output());
        }

        return $result->output();
    }

    private function php(string $code): string
    {
        return trim($this->console(['tinker', '--execute='.$code]));
    }

    private function install(): void
    {
        $this->console(['ecomodular:install', '--no-interaction', '--store-name=Drill Store', '--name=Drill Owner', '--email=owner@drill.test']);
    }

    public function test_fresh_install_from_an_empty_database()
    {
        $this->console(['migrate', '--force']);
        $this->install();

        $this->assertSame('1', $this->php("echo App\\Models\\User::query()->where('email', 'owner@drill.test')->count();"));
        $this->assertStringContainsString('cannot be re-run', $this->console(['ecomodular:install', '--no-interaction'], mustSucceed: false));
        $this->assertStringContainsString('Up to date', $this->console(['ecomodular:health']));
    }

    public function test_upgrade_from_an_earlier_schema_keeps_data()
    {
        // A store installed with the Phase 2 schema (catalog and orders, before payments, modules, themes and the marketplace).
        $old = $this->dir.'/old-migrations';
        File::ensureDirectoryExists($old);
        foreach (File::files(database_path('migrations')) as $file) {
            if (strcmp($file->getFilename(), '2026_10_14') < 0) {
                File::copy($file->getPathname(), $old.'/'.$file->getFilename());
            }
        }

        $this->console(['migrate', '--force', '--realpath', '--path='.$old]);
        // Installed by that release (today's installer rightly refuses while migrations are pending).
        $this->php("app(App\Core\Installation\InstallPlatform::class)->handle(['name' => 'Old Owner', 'email' => 'owner@drill.test', 'password' => 'Drill-Owner-Password-1!', 'store_name' => 'Old Store', 'default_currency' => 'USD', 'timezone' => 'UTC']);");
        $this->php("App\\Domains\\Catalog\\Models\\Product::factory()->withVariant('12.50', 4)->create(['name' => 'Legacy Vase']);");

        // Upgrade: the remaining migrations apply on top of real data.
        $this->console(['migrate', '--force']);

        $this->assertSame('Legacy Vase|12.5000|0', $this->php(
            "\$p = App\\Domains\\Catalog\\Models\\Product::query()->where('name', 'Legacy Vase')->firstOrFail(); "
            ."echo \$p->name.'|'.\$p->variants()->first()->prices()->first()->amount.'|'.app(App\\Core\\Installation\\RequirementChecker::class)->pendingMigrations();"
        ));
        $this->assertStringContainsString('Up to date', $this->console(['ecomodular:health']));
    }

    public function test_backup_damage_and_restore_drill()
    {
        $this->console(['migrate', '--force']);
        $this->install();
        $this->php("App\\Domains\\Catalog\\Models\\Product::factory()->withVariant('30.00', 2)->create(['name' => 'Precious Bowl']);");
        File::put($this->dir.'/root/storage/app/public/products/bowl.webp', 'original-image');

        $this->assertStringContainsString('AES-256 encrypted', $this->console(['ecomodular:backup']));
        $name = basename((string) (glob($this->dir.'/backups/ecomodular-backup-*.zip')[0] ?? ''));
        $this->assertNotSame('', $name);

        // Incident: data and files are lost.
        $this->php("App\\Domains\\Catalog\\Models\\Product::query()->update(['name' => 'Overwritten']);");
        File::delete($this->dir.'/root/storage/app/public/products/bowl.webp');
        File::deleteDirectory($this->dir.'/root/themes');

        $this->assertStringContainsString('Archive verified', $this->console(['ecomodular:restore', $name, '--verify-only']));
        $this->assertStringContainsString('Restored', $this->console(['ecomodular:restore', $name, '--force']));

        $this->assertSame('1', $this->php("echo App\\Domains\\Catalog\\Models\\Product::query()->where('name', 'Precious Bowl')->count();"));
        $this->assertSame('original-image', File::get($this->dir.'/root/storage/app/public/products/bowl.webp'));
        $this->assertFileExists($this->dir.'/root/themes/ecomodular/essence/theme.json');
        $this->assertSame('1', $this->php("echo App\\Models\\User::query()->where('email', 'owner@drill.test')->count();"));
    }
}
