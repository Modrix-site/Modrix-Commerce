<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Themes\ThemeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

/**
 * Themes installed from Modrix Cloud or a ZIP ship prebuilt screens in dist/
 * (ecomodular:module:build); /theme-build serves them so the storefront
 * renders the theme without rebuilding the store's assets.
 */
class ThemeBuildTest extends TestCase
{
    use RefreshDatabase, ThemeFixtures;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpThemes();

        $this->dir = $this->makeTheme(overrides: ['frontend' => ['index' => 'src/index.tsx', 'stylesheet' => 'src/style.css']]);
        File::ensureDirectoryExists($this->dir.'/src');
        File::put($this->dir.'/src/index.tsx', 'export default function Index() { return null; }');
        File::put($this->dir.'/src/style.css', '.x { color: red; }');
        File::ensureDirectoryExists($this->dir.'/dist/src');
        File::put($this->dir.'/dist/theme-ui.json', json_encode(['sdk' => 1, 'entries' => ['src/index.tsx' => 'src/index-abc.js'], 'css' => ['package-style-abc.css']]));
        File::put($this->dir.'/dist/src/index-abc.js', 'export default function Index() { return null; }');
        File::put($this->dir.'/dist/package-style-abc.css', '.x{color:red}');
    }

    private function theme(): Extension
    {
        app(ThemeRepository::class)->sync();

        return Extension::query()->where('type', 'theme')->where('name', 'acme/sunny')->firstOrFail();
    }

    public function test_a_theme_with_prebuilt_screens_is_valid_and_its_files_are_served()
    {
        $this->assertNull($this->theme()->errors);
        $this->assertNotSame(ModuleState::Invalid, $this->theme()->state);

        $this->get('/theme-build/acme/sunny/theme-ui.json')->assertOk()->assertHeader('Content-Type', 'application/json');
        $script = $this->get('/theme-build/acme/sunny/src/index-abc.js')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('text/javascript', (string) $script->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $script->headers->get('Cache-Control'));
        $this->get('/theme-build/acme/sunny/package-style-abc.css')->assertOk();
    }

    public function test_only_dist_scripts_styles_and_the_index_are_served()
    {
        $this->theme();

        $this->get('/theme-build/acme/sunny/../theme.json')->assertNotFound();
        $this->get('/theme-build/acme/sunny/src/index.tsx')->assertNotFound();
        $this->get('/theme-build/acme/unknown/theme-ui.json')->assertNotFound();
        File::put($this->dir.'/dist/other.json', '{}');
        $this->get('/theme-build/acme/sunny/other.json')->assertNotFound();
    }

    public function test_dist_may_not_hold_anything_but_scripts_styles_and_json()
    {
        File::put($this->dir.'/dist/server.php', '<?php echo "no";');

        $this->assertSame(ModuleState::Invalid, $this->theme()->state);
        $this->get('/theme-build/acme/sunny/theme-ui.json')->assertNotFound();
    }
}
