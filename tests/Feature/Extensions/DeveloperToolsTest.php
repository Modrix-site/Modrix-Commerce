<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Core\Marketplace\PackageScanner;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ModuleFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

/**
 * Phase 8.1–8.2: scaffolders, the package command and the security scanner.
 */
class DeveloperToolsTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase, ThemeFixtures;

    private string $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        $this->setUpThemes(withBundled: true);
        $this->output = storage_path('framework/testing/packages-'.Str::random(8));
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->output));
    }

    public function test_a_scaffolded_module_validates_installs_enables_and_works()
    {
        $this->artisan('ecomodular:make:module', ['name' => 'acme/gift-notes', '--author' => 'Acme Ltd'])
            ->expectsOutputToContain('created and discovered')
            ->assertSuccessful();

        $owner = $this->owner();
        $manager = app(ModuleManager::class);
        $manager->install('acme/gift-notes', $owner);
        $manager->enable('acme/gift-notes', trusted: true, actor: $owner);
        $this->assertSame(ModuleState::Enabled, Extension::query()->where('name', 'acme/gift-notes')->firstOrFail()->state);

        // The generated admin page, permission and setting work as generated.
        $this->withoutVite();
        // The page lives in the temporary module directory, outside the configured page paths.
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->actingAs($owner)->get('/admin/m/acme/gift-notes')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('module/acme/gift-notes/admin/index')->has('items', 0));
        $this->post('/admin/m/acme/gift-notes/items', ['name' => 'Wrap in paper'])->assertRedirect();
        $this->assertDatabaseHas('acme_gift_notes_items', ['name' => 'Wrap in paper']);
        $this->actingAs($this->userWithRole('analyst'))->get('/admin/m/acme/gift-notes')->assertForbidden();

        $manager->configure('acme/gift-notes', ['max_items' => '1'], $owner);
        $this->actingAs($owner)->post('/admin/m/acme/gift-notes/items', ['name' => 'Second'])->assertSessionHasErrors('name');

        // The scaffold is clean for the scanner.
        $summary = PackageScanner::summary(app(PackageScanner::class)->scan($this->modulesPath.'/acme/gift-notes'));
        $this->assertSame(0, $summary['critical'] + $summary['high']);
    }

    public function test_scaffolders_refuse_bad_names_and_existing_packages()
    {
        $this->artisan('ecomodular:make:module', ['name' => 'NotValid'])->assertFailed();
        $this->artisan('ecomodular:make:module', ['name' => 'acme/twice'])->assertSuccessful();
        $this->artisan('ecomodular:make:module', ['name' => 'acme/twice'])->expectsOutputToContain('already exists')->assertFailed();
        $this->artisan('ecomodular:make:theme', ['name' => 'acme/x', '--from' => 'nobody/missing'])->assertFailed();
    }

    public function test_a_scaffolded_theme_validates_and_activates()
    {
        $this->artisan('ecomodular:make:theme', ['name' => 'acme/harbor', '--from' => 'ecomodular/noir'])
            ->expectsOutputToContain('created from ecomodular/noir')
            ->assertSuccessful();

        $manifest = json_decode((string) File::get($this->themesPath.'/acme/harbor/theme.json'), true);
        $this->assertSame(['acme/harbor', '0.1.0', '^1.0'], [$manifest['name'], $manifest['version'], $manifest['platform_version']]);
        $this->assertFileExists($this->themesPath.'/acme/harbor/assets/images/hero.webp');

        app(ThemeManager::class)->activate('acme/harbor');
        $this->assertSame('acme/harbor', app(ThemeRepository::class)->active()?->name);
    }

    public function test_package_command_builds_the_archive_the_marketplace_accepts()
    {
        $this->artisan('ecomodular:make:module', ['name' => 'acme/gift-notes'])->assertSuccessful();

        $this->artisan('ecomodular:package', ['name' => 'acme/gift-notes', '--output' => $this->output])
            ->expectsOutputToContain('Scan: 0 critical, 0 high')
            ->assertSuccessful();
        $this->assertFileExists($this->output.'/acme-gift-notes-0.1.0.zip');

        $this->artisan('ecomodular:package', ['name' => 'ecomodular/bloom', '--type' => 'theme', '--output' => $this->output])->assertSuccessful();
        $this->assertFileExists($this->output.'/ecomodular-bloom-1.0.0.zip');
    }

    public function test_package_command_reports_invalid_and_dangerous_packages()
    {
        $this->artisan('ecomodular:make:module', ['name' => 'acme/risky'])->assertSuccessful();
        File::put($this->modulesPath.'/acme/risky/src/Backdoor.php', <<<'PHP'
            <?php
            namespace Acme\Risky;
            class Backdoor
            {
                public function run(string $cmd): void
                {
                    shell_exec($cmd);
                }
            }
            PHP);

        $this->artisan('ecomodular:package', ['name' => 'acme/risky', '--output' => $this->output, '--strict' => true])
            ->expectsOutputToContain('Strict mode')
            ->assertFailed();

        File::put($this->modulesPath.'/acme/risky/module.json', '{"name": "acme/other"}');
        $this->artisan('ecomodular:package', ['name' => 'acme/risky', '--output' => $this->output])
            ->expectsOutputToContain('not valid')
            ->assertFailed();
    }

    public function test_scanner_flags_dangerous_patterns_with_file_and_line()
    {
        $dir = $this->modulesPath.'/scan-sample';
        File::ensureDirectoryExists($dir.'/src');
        File::put($dir.'/src/Bad.php', <<<'PHP'
            <?php
            namespace Acme\Bad;

            use Illuminate\Support\Facades\DB;

            class Bad
            {
                public function a($x) { eval($x); }
                public function b($x) { return `ls $x`; }
                public function c($x) { return \system($x); }
                public function d($x) { DB::unprepared($x); }
                public function e($f, $x) { return $f($x); }
                public function f($p) { require $p; }
                public function g() { return unserialize($_COOKIE['x']); }
                public function header() { return new Mail(); }
                public function h($m) { return $this->exec($m); }
            }
            PHP);
        File::put($dir.'/payload.phar', 'x');
        File::put($dir.'/.htaccess', 'x');
        File::ensureDirectoryExists($dir.'/resources/js');
        File::put($dir.'/resources/js/x.tsx', "const run = (s: string) => eval(s);\n");

        $findings = app(PackageScanner::class)->scan($dir);
        $at = fn (string $rule, int $line) => collect($findings)->contains(fn ($f) => $f['rule'] === $rule && $f['line'] === $line);

        $this->assertTrue($at('eval', 8));
        $this->assertTrue($at('shell', 9));
        $this->assertTrue($at('function', 10));
        $this->assertTrue($at('static-call', 11));
        $this->assertTrue($at('variable-function', 12));
        $this->assertTrue($at('dynamic-include', 13));
        $this->assertTrue($at('function', 14));
        // Method calls and class names that share a function name are not reported.
        $this->assertFalse($at('function', 15));
        $this->assertFalse($at('function', 16));
        $this->assertSame(2, collect($findings)->where('rule', 'forbidden-file')->count());
        $this->assertTrue($at('script', 1));
        $this->assertSame('critical', $findings[0]['severity']);
    }
}
