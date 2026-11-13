<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * /module-assets serves a module's prebuilt screens (dist/), so modules
 * installed from Modrix Cloud or a ZIP work without rebuilding the store.
 */
class ModuleAssetTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();

        $this->dir = $this->makeModule('acme/refunds', []);
        $this->writeFile($this->dir.'/dist/module-ui.json', json_encode(['sdk' => 1, 'pages' => ['admin/index' => 'pages/admin/index-abc.js'], 'slots' => [], 'css' => ['module-style-abc.css']]));
        $this->writeFile($this->dir.'/dist/pages/admin/index-abc.js', 'export default function Page() {}');
        $this->writeFile($this->dir.'/dist/module-style-abc.css', '.x{color:red}');
        $this->writeFile($this->dir.'/dist/notes.json', '{}');
        $this->writeFile($this->dir.'/src/Secret.php', '<?php // not served');

        ModuleRepository::flushBootCache();
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('acme/refunds');
        app(ModuleManager::class)->enable('acme/refunds', true);
    }

    private function url(string $path): string
    {
        return '/module-assets/acme/refunds/'.$path;
    }

    public function test_the_index_scripts_and_styles_of_an_enabled_module_are_served()
    {
        $this->get($this->url('module-ui.json'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'no-cache, public');

        $script = $this->get($this->url('pages/admin/index-abc.js'))->assertOk();
        $this->assertStringStartsWith('text/javascript', (string) $script->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $script->headers->get('Cache-Control'));
        $script->assertHeader('X-Content-Type-Options', 'nosniff');

        $style = $this->get($this->url('module-style-abc.css'))->assertOk();
        $this->assertStringStartsWith('text/css', (string) $style->headers->get('Content-Type'));
    }

    public function test_nothing_outside_dist_or_of_another_type_is_served()
    {
        $this->get($this->url('../module.json'))->assertNotFound();
        $this->get($this->url('../src/Secret.php'))->assertNotFound();
        $this->get('/module-assets/acme/refunds/src/Secret.php')->assertNotFound();
        $this->get($this->url('notes.json'))->assertNotFound();
        $this->get($this->url('missing.js'))->assertNotFound();
    }

    public function test_disabled_or_unknown_modules_serve_nothing()
    {
        app(ModuleManager::class)->disable('acme/refunds');

        $this->get($this->url('module-ui.json'))->assertNotFound();
        $this->get('/module-assets/acme/unknown/module-ui.json')->assertNotFound();
    }
}
