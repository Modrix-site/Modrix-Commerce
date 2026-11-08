<?php

namespace Tests\Feature\Updates;

use App\Core\Updates\CorePackage;
use App\Core\Updates\CoreUpdateError;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class CorePackageCommandTest extends TestCase
{
    private string $root;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/core-root-'.Str::random(8));
        $this->out = $this->root.'-out';

        $files = [
            'app/Core/Platform.php' => "<?php\n\nnamespace App\\Core;\n\nfinal class Platform\n{\n    public const VERSION = '1.0.0';\n}\n",
            'bootstrap/app.php' => '<?php return 1;',
            'bootstrap/providers.php' => '<?php return [];',
            'bootstrap/cache/packages.php' => '<?php return [];',
            'config/app.php' => '<?php return [];',
            'config/.env.local' => 'SECRET=1',
            'database/migrations/.gitkeep' => '',
            'lang/en/x.php' => '<?php return [];',
            'public/build/manifest.json' => '{}',
            'public/index.php' => '<?php',
            'public/storage/upload.jpg' => 'x',
            'resources/views/x.blade.php' => 'x',
            'routes/web.php' => '<?php',
            'vendor/autoload.php' => '<?php',
            'vendor/acme/lib/.git/HEAD' => 'ref',
            'artisan' => '#!/usr/bin/env php',
            'composer.json' => '{}',
            'composer.lock' => '{}',
            'package.json' => '{}',
            '.env' => 'APP_KEY=secret',
            '.env.example' => 'APP_KEY=',
            'storage/x' => 'x',
            'modules/a/module.json' => '{}',
            'themes/b/theme.json' => '{}',
            'node_modules/y' => 'y',
            'tests/z' => 'z',
            '.git/HEAD' => 'ref',
        ];

        foreach ($files as $name => $content) {
            File::ensureDirectoryExists(dirname($this->root.'/'.$name));
            File::put($this->root.'/'.$name, $content);
        }

        config(['ecomodular.updates.root' => $this->root]);
        $this->beforeApplicationDestroyed(function () {
            File::deleteDirectory($this->root);
            File::deleteDirectory($this->out);
        });
    }

    /** @return list<string> */
    private function entries(string $zipPath): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();

        return $names;
    }

    public function test_package_contains_managed_paths_only(): void
    {
        $this->artisan('ecomodular:core-package', ['--output' => $this->out])->assertSuccessful();

        $zipPath = $this->out.'/modrix-commerce-1.0.0.zip';
        $this->assertFileExists($zipPath);
        $names = $this->entries($zipPath);

        foreach (['app/Core/Platform.php', 'vendor/autoload.php', 'artisan', 'bootstrap/app.php', 'public/build/manifest.json', 'composer.lock'] as $expected) {
            $this->assertContains($expected, $names);
        }

        foreach ($names as $name) {
            $this->assertTrue(CorePackage::safeEntry($name), $name);
            $this->assertDoesNotMatchRegularExpression('#^(\.env|storage/|modules/|themes/|node_modules/|tests/|\.git/|public/storage|bootstrap/cache/)#', $name);
            $this->assertStringNotContainsString('/.git/', '/'.$name);
            $this->assertStringNotContainsString('.env.local', $name);
        }
    }

    public function test_package_never_contains_database_files(): void
    {
        foreach (['database/database.sqlite', 'database/migrations/x.sqlite3', 'database/seeders/x.db', 'database/database.sqlite-journal',
            'database/database.sqlite-wal', 'database/database.sqlite-shm', 'app/cache.db'] as $name) {
            File::ensureDirectoryExists(dirname($this->root.'/'.$name));
            File::put($this->root.'/'.$name, 'SQLite format 3');
        }
        File::ensureDirectoryExists($this->root.'/database/seeders');
        File::put($this->root.'/database/seeders/DatabaseSeeder.php', '<?php');

        $this->artisan('ecomodular:core-package', ['--output' => $this->out])->assertSuccessful();
        $names = $this->entries($this->out.'/modrix-commerce-1.0.0.zip');

        $this->assertContains('database/seeders/DatabaseSeeder.php', $names);
        $this->assertContains('database/migrations/.gitkeep', $names);
        foreach ($names as $name) {
            $this->assertFalse(CorePackage::isDatabaseFile($name), $name);
            $this->assertDoesNotMatchRegularExpression('/\.(sqlite3?|db)$|-(journal|wal|shm)$/', $name);
        }
    }

    public function test_extract_refuses_database_files(): void
    {
        $zipPath = $this->root.'-db.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('app/x.php', '<?php');
        $zip->addFromString('database/migrations/live.sqlite', 'SQLite format 3');
        $zip->close();
        $this->beforeApplicationDestroyed(fn () => File::delete($zipPath));

        try {
            (new CorePackage)->extract($zipPath, $this->out.'/x', 10_000_000);
            $this->fail('Extracted a database file');
        } catch (CoreUpdateError $e) {
            $this->assertStringContainsString('database file', $e->getMessage());
        }

        $this->assertDirectoryDoesNotExist($this->out.'/x');
    }

    public function test_extract_reports_an_unwritable_entry_without_the_path(): void
    {
        $zipPath = $this->root.'-ok.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('app/x.php', '<?php');
        $zip->close();
        $this->beforeApplicationDestroyed(fn () => File::delete($zipPath));
        // 'xb' refuses an existing file, standing in for any open failure.
        File::ensureDirectoryExists($this->out.'/x/app');
        File::put($this->out.'/x/app/x.php', 'already here');

        try {
            (new CorePackage)->extract($zipPath, $this->out.'/x', 10_000_000);
            $this->fail('Overwrote an existing file');
        } catch (CoreUpdateError $e) {
            $this->assertSame('The package entry app/x.php could not be extracted.', $e->getMessage());
        }
    }

    public function test_extract_refuses_an_entry_whose_size_does_not_match(): void
    {
        $zipPath = $this->root.'-size.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('app/x.php', str_repeat('a', 100));
        $zip->setCompressionName('app/x.php', ZipArchive::CM_STORE);
        $zip->close();
        $this->beforeApplicationDestroyed(fn () => File::delete($zipPath));

        // Declare 50 bytes in the central directory for a 100-byte stored entry.
        $bytes = (string) file_get_contents($zipPath);
        $central = strpos($bytes, "PK\x01\x02");
        $this->assertNotFalse($central);
        $bytes = substr_replace($bytes, pack('V', 50), $central + 24, 4);
        file_put_contents($zipPath, $bytes);

        $this->expectException(CoreUpdateError::class);
        $this->expectExceptionMessage('size mismatch');
        (new CorePackage)->extract($zipPath, $this->out.'/x', 10_000_000);
    }

    public function test_package_is_refused_without_vendor_or_built_assets(): void
    {
        File::delete($this->root.'/public/build/manifest.json');

        $this->artisan('ecomodular:core-package', ['--output' => $this->out])->assertFailed();
        $this->assertFileDoesNotExist($this->out.'/modrix-commerce-1.0.0.zip');
    }

    public function test_safe_entry_rules(): void
    {
        foreach (['app/x.php', 'vendor/', 'public/build/assets/a-1.js'] as $ok) {
            $this->assertTrue(CorePackage::safeEntry($ok), $ok);
        }

        foreach (['', '../x.php', 'app/../../x.php', '/etc/passwd', 'C:/x.php', 'app\\x.php', 'app/./x.php', "app/x\0.php", 'app//x.php', 'app/x.php:stream'] as $bad) {
            $this->assertFalse(CorePackage::safeEntry($bad), $bad);
        }
    }
}
