<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Themes\ThemeCustomizations;
use App\Core\Themes\ThemeException;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class ThemeEngineTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpThemes(withBundled: true);
        // A bundled theme as the store default, whatever the deployment sets.
        config(['ecomodular.themes.default' => 'modrixcommerce/prism']);
    }

    private function theme(string $name = 'acme/sunny'): Extension
    {
        app(ThemeRepository::class)->sync();

        return Extension::query()->where('type', 'theme')->where('name', $name)->firstOrFail();
    }

    public function test_section_image_upload_returns_selectable_sanitized_image(): void
    {
        Storage::fake('public');
        $response = $this->actingAs($this->userWithRole('administrator'))->post(route('admin.themes.uploads.store'), [
            'file' => UploadedFile::fake()->image('section.png', 100, 60),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonStructure(['image' => ['value', 'label', 'url']]);
        $this->assertStringStartsWith('media:themes/', $response->json('image.value'));
        $this->makeTheme();
        app(ThemeManager::class)->activate('acme/sunny');
        app(ThemeManager::class)->customizeSections('acme/sunny', 'home', [['type' => 'hero', 'settings' => ['heading' => 'Uploaded section', 'image' => $response->json('image.value')]]]);
        $manifest = app(ThemeManager::class)->renderedManifest($this->theme());
        $this->assertSame($response->json('image.value'), app(ThemeCustomizations::class)->sections($manifest, 'home')[0]['settings']['image']);
    }

    public function test_themes_load_their_own_settings_file_and_reject_external_paths(): void
    {
        $dir = $this->makeTheme(overrides: ['customizer_file' => 'settings.json']);
        File::put($dir.'/settings.json', json_encode(['title' => 'Own customizer', 'fields' => ['custom.cart_heading' => ['type' => 'string', 'label' => 'Cart heading', 'default' => 'Shopping bag', 'max' => 120]]], JSON_THROW_ON_ERROR));
        $theme = $this->theme();
        $this->assertNull($theme->errors);
        $this->assertSame('Shopping bag', app(ThemeManager::class)->renderedManifest($theme)->defaults()['custom.cart_heading']);
        $manifest = json_decode(File::get($dir.'/theme.json'), true);
        $manifest['customizer_file'] = '../settings.json';
        File::put($dir.'/theme.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $this->assertSame(ModuleState::Invalid, $this->theme()->state);
    }

    public function test_disabled_themes_stay_disabled_and_can_be_enabled_again(): void
    {
        $this->makeTheme();
        $manager = app(ThemeManager::class);
        $manager->activate('acme/sunny');
        $manager->customizeDesign('acme/sunny', ['color.primary' => '#123456']);
        $manager->disable('acme/sunny');
        app(ThemeRepository::class)->sync();
        $this->assertSame(ModuleState::Disabled, $this->theme()->state);
        $this->assertNull(app(ThemeRepository::class)->active());
        $manager->activate('acme/sunny');
        $this->assertSame(ModuleState::Enabled, $this->theme()->state);
        $this->assertSame('#123456', app(ThemeCustomizations::class)->designOverrides($manager->renderedManifest($this->theme()))['color.primary']);
    }

    public function test_theme_catalog_fields_override_defaults_and_are_shared_with_storefront(): void
    {
        config(['inertia.ssr.enabled' => false]);
        $this->makeTheme(overrides: ['customizer' => ['title' => 'Catalog settings', 'fields' => [
            'custom.catalog_heading' => ['type' => 'string', 'label' => 'Catalog heading', 'default' => 'Our products', 'max' => 120],
            'custom.catalog_show_filters' => ['type' => 'boolean', 'label' => 'Show filters', 'default' => true],
        ]]]);
        $manager = app(ThemeManager::class);
        $manager->activate('acme/sunny');
        $manager->customizeDesign('acme/sunny', ['custom.catalog_heading' => 'Browse products', 'custom.catalog_show_filters' => false]);
        $this->get('/search')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.settings.catalog_heading', 'Browse products')
            ->where('storefront.theme.settings.catalog_show_filters', false));
        $manager->customizeDesign('acme/sunny', ['custom.catalog_heading' => '']);
        $this->get('/search')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.settings.catalog_heading', 'Our products'));
    }

    public function test_frontend_theme_entries_must_exist_and_cannot_declare_php(): void
    {
        $dir = $this->makeTheme(overrides: ['frontend' => ['index' => 'src/index.tsx', 'catalog' => 'src/catalog.tsx', 'stylesheet' => 'src/style.css']]);
        File::ensureDirectoryExists($dir.'/src');
        File::put($dir.'/src/index.tsx', 'export default function Index() { return null; }');
        File::put($dir.'/src/catalog.tsx', 'export default function Catalog() { return null; }');
        File::put($dir.'/src/style.css', '.theme-example { color: red; }');
        $this->assertNull($this->theme()->errors);
        File::put($dir.'/src/server.php', '<?php echo "no";');
        $this->assertSame(ModuleState::Invalid, $this->theme()->state);
    }

    public function test_deleting_theme_archives_files_and_active_theme_must_be_disabled_first(): void
    {
        $this->makeTheme();
        $manager = app(ThemeManager::class);
        $manager->activate('acme/sunny');
        try {
            $manager->delete('acme/sunny');
            $this->fail('An active theme must not be deleted.');
        } catch (ThemeException $e) {
            $this->assertStringContainsString('Disable', $e->getMessage());
        }
        $manager->disable('acme/sunny');
        $manager->delete('acme/sunny');
        $this->assertDirectoryDoesNotExist($this->themesPath.'/acme/sunny');
        $this->assertNull(app(ThemeRepository::class)->find('acme/sunny'));
        $this->assertNotEmpty(glob(storage_path('app/private/theme-archives/acme-sunny-*.zip')));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidThemes(): array
    {
        return [
            'path traversal in an image' => [['design' => ['logo' => 'asset:../../../.env']], 'design.logo'],
            'absolute asset directory' => [['assets' => '/etc'], 'assets must be an existing directory'],
            'script link' => [['sections' => ['home' => [['type' => 'hero', 'settings' => ['heading' => 'x', 'button_link' => 'javascript:alert(1)']]]]], 'Button link'],
            'protocol-relative link' => [['sections' => ['home' => [['type' => 'promo_banner', 'settings' => ['text' => 'x', 'button_link' => '//evil.test']]]]], 'Button link'],
            'unknown section' => [['sections' => ['home' => [['type' => 'custom_html', 'settings' => ['html' => '<script>']]]]], 'not a known section type'],
            'css injection in a color' => [['design' => ['color.primary' => 'red;}body{display:none']], 'design.color.primary'],
            'unknown font' => [['design' => ['font_body' => 'Comic Sans; src:url(x)']], 'design.font_body'],
            'unknown design key' => [['design' => ['custom_css' => 'body{}']], 'unknown setting "custom_css"'],
            'incompatible platform' => [['platform_version' => '^9.0'], 'Requires platform version'],
            'wrong type' => [['type' => 'module'], 'type must be "theme"'],
            'sections without capability' => [['capabilities' => []], 'requires the "home.sections" capability'],
            'unknown capability' => [['capabilities' => ['home.sections', 'run_php']], 'Unknown capability "run_php"'],
            'fonts without capability' => [['fonts' => [['family' => 'Fancy', 'file' => 'assets/fonts/fancy.woff2']]], 'requires the "custom_fonts" capability'],
            'palette with unknown field' => [['customizer' => ['presets' => ['warm' => ['custom_css' => '#fff']]]], 'customizer.presets.warm.custom_css is not a design setting'],
            'palette with invalid value' => [['customizer' => ['presets' => ['warm' => ['color.primary' => 'red']]]], 'customizer.presets.warm.color.primary must be a color'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidThemes')]
    public function test_invalid_theme_packages_are_rejected(array $overrides, string $expected)
    {
        $this->makeTheme(overrides: $overrides);
        $theme = $this->theme();

        $this->assertSame(ModuleState::Invalid, $theme->state);
        $this->assertStringContainsString($expected, implode("\n", (array) $theme->errors));
    }

    public function test_themes_may_not_contain_code_or_markup_files()
    {
        $dir = $this->makeTheme();
        File::put($dir.'/assets/images/logo.svg', '<svg onload="alert(1)"/>');
        File::put($dir.'/helper.php', '<?php echo 1;');

        $errors = implode("\n", (array) $this->theme()->errors);

        $this->assertStringContainsString('assets/images/logo.svg is not allowed', $errors);
        $this->assertStringContainsString('helper.php is not allowed', $errors);
    }

    public function test_the_bundled_default_theme_is_valid_and_used_until_another_is_activated()
    {
        $this->assertSame(ModuleState::Discovered, $this->theme('modrixcommerce/prism')->state);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.name', 'modrixcommerce/prism')
            ->where('storefront.theme.source', 'default')
            ->where('sections.0.type', 'hero'));
    }

    public function test_the_storefront_falls_back_to_the_builtin_design_without_any_theme()
    {
        File::deleteDirectory($this->themesPath.'/modrixcommerce');

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.source', 'builtin')
            ->has('sections', 3));
    }

    public function test_activating_a_theme_changes_the_storefront_design_and_layout()
    {
        $this->makeTheme();
        app(ThemeManager::class)->activate('acme/sunny', $this->owner());

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.name', 'acme/sunny')
            ->where('storefront.theme.source', 'active')
            ->where('storefront.theme.layout.header', 'centered')
            ->where('storefront.theme.layout.gridColumns', 3)
            ->where('storefront.theme.css', fn (string $css) => str_contains($css, '--primary:#ff6600') && str_contains($css, '--font-heading:ui-serif'))
            ->where('sections.0.settings.heading', 'Sunny days')
            ->where('sections.0.data.image', fn (string $url) => str_contains($url, '/theme-assets/acme/sunny/assets/images/hero.png')));

        $this->assertSame(ModuleState::Discovered, $this->theme('modrixcommerce/prism')->state);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.activated']);
    }

    public function test_only_one_theme_is_active()
    {
        $this->makeTheme();
        $this->makeTheme('acme/moon');
        $manager = app(ThemeManager::class);

        $manager->activate('acme/sunny');
        $manager->activate('acme/moon');

        $this->assertSame(['acme/moon'], Extension::query()->where('type', 'theme')->where('state', ModuleState::Enabled)->pluck('name')->all());
    }

    public function test_invalid_themes_cannot_be_activated()
    {
        $this->makeTheme(overrides: ['platform_version' => '^9.0']);

        $this->expectException(ThemeException::class);
        app(ThemeManager::class)->activate('acme/sunny');
    }

    public function test_merchant_customizations_survive_updates_and_switching()
    {
        $this->makeTheme();
        $manager = app(ThemeManager::class);
        $manager->activate('acme/sunny');
        $manager->customizeDesign('acme/sunny', ['color.primary' => '#123456', 'announcement' => 'Free shipping over $50']);
        $manager->customizeSections('acme/sunny', 'home', [['type' => 'rich_text', 'settings' => ['body' => 'Our story']]]);

        // New release on disk: still rendering the applied snapshot until the update is applied.
        $this->makeTheme(overrides: ['version' => '1.1.0', 'design' => ['color.primary' => '#00aa00', 'layout.header' => 'minimal'],
            'changelog' => [['version' => '1.1.0', 'date' => '2026-02-01', 'notes' => 'Minimal header.']]]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.version', '1.0.0')->where('storefront.theme.layout.header', 'centered'));

        $manager->applyUpdate('acme/sunny');

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.version', '1.1.0')
            ->where('storefront.theme.layout.header', 'minimal')
            ->where('storefront.theme.css', fn (string $css) => str_contains($css, '--primary:#123456'))
            ->where('storefront.theme.announcement', 'Free shipping over $50')
            ->where('sections.0.type', 'rich_text'));

        // Switching away and back keeps them too.
        $manager->activate('modrixcommerce/prism');
        $manager->activate('acme/sunny');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.announcement', 'Free shipping over $50'));
    }

    public function test_overrides_a_new_version_no_longer_accepts_are_reported_not_deleted()
    {
        $dir = $this->makeTheme();
        $manager = app(ThemeManager::class);
        $manager->activate('acme/sunny');
        $manager->customizeDesign('acme/sunny', ['logo' => 'asset:assets/images/hero.png']);

        File::delete($dir.'/assets/images/hero.png');
        $this->writePng($dir.'/assets/images/new-hero.png');
        $this->makeTheme(overrides: ['version' => '1.1.0', 'sections' => ['home' => [['type' => 'hero', 'settings' => ['heading' => 'New', 'image' => 'asset:assets/images/new-hero.png']]]]], withImage: false);

        $result = $manager->applyUpdate('acme/sunny');

        $this->assertSame(['logo'], $result['ignored']);
        $manifest = ThemeManifest::fromArray((array) $this->theme()->applied_manifest);
        $this->assertSame('asset:assets/images/hero.png', app(ThemeCustomizations::class)->designOverrides($manifest)['logo']);
    }

    public function test_invalid_customizations_are_refused()
    {
        $this->makeTheme();
        $manager = app(ThemeManager::class);

        try {
            $manager->customizeDesign('acme/sunny', ['color.primary' => 'url(javascript:1)']);
            $this->fail('Invalid color accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('color.primary', $e->errors());
        }

        try {
            $manager->customizeSections('acme/sunny', 'home', [['type' => 'hero', 'settings' => ['heading' => 'Hi', 'button_link' => 'data:text/html,x']]]);
            $this->fail('Unsafe link accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sections.0.button_link', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $manager->customizeDesign('acme/sunny', ['logo' => 'media:themes/'.str_repeat('a', 40).'.png']); // not an uploaded file
    }

    public function test_theme_palette_presets_are_exposed_and_applied_as_design_overrides()
    {
        $this->makeTheme(overrides: ['customizer' => [
            'title' => 'Brand',
            'presets' => [
                'warm' => ['color.primary' => '#aa3300', 'font_heading' => 'serif', 'radius' => '1rem'],
                'cool' => ['color.primary' => '#0033aa', 'layout.header' => 'minimal'],
            ],
        ]]);

        $theme = $this->theme();
        $this->assertSame(ModuleState::Discovered, $theme->state, implode("\n", (array) $theme->errors));

        $manifest = app(ThemeManager::class)->renderedManifest($theme);
        $this->assertEqualsCanonicalizing(['warm', 'cool'], array_keys($manifest->presets()));

        $manager = app(ThemeManager::class);
        $manager->activate('acme/sunny');

        // Applying a palette is an ordinary design change; every field remains editable after.
        $manager->customizeDesign('acme/sunny', $manifest->presets()['warm']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where(
            'storefront.theme.css',
            fn (string $css) => str_contains($css, '--primary:#aa3300') && str_contains($css, '--radius:1rem'),
        ));
        $this->assertSame('serif', app(ThemeCustomizations::class)->designOverrides($manifest)['font_heading']);
    }

    public function test_reset_requires_confirmation_and_restores_defaults()
    {
        $this->makeTheme();
        $manager = app(ThemeManager::class);
        $manager->activate('acme/sunny');
        $manager->customizeDesign('acme/sunny', ['color.primary' => '#123456']);

        try {
            $manager->reset('acme/sunny', 'yes');
            $this->fail('Reset without confirmation.');
        } catch (ThemeException) {
        }

        $manager->reset('acme/sunny', 'acme/sunny');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.css', fn (string $css) => str_contains($css, '--primary:#ff6600')));
    }

    public function test_preview_is_visible_only_to_the_previewing_staff_member()
    {
        $this->makeTheme();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.themes.preview', $this->theme()))->assertRedirect(route('home'));
        $this->actingAs($owner)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.name', 'acme/sunny')
            ->where('storefront.theme.source', 'preview')
            ->where('storefront.themePreview.name', 'acme/sunny'));

        // Shoppers (other sessions) keep the active theme.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.name', 'modrixcommerce/prism')->where('storefront.themePreview', null));

        // A preview value in a customer's session is ignored.
        $this->actingAs(User::factory()->create())->withSession(['theme_preview' => 'acme/sunny'])
            ->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.name', 'modrixcommerce/prism'));
    }

    public function test_active_theme_with_missing_files_falls_back_and_recovers()
    {
        $dir = $this->makeTheme();
        app(ThemeManager::class)->activate('acme/sunny');

        File::moveDirectory($dir, $this->themesPath.'/held');
        $this->assertSame(ModuleState::Failed, $this->theme()->state);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('storefront.theme.name', 'modrixcommerce/prism'));

        File::moveDirectory($this->themesPath.'/held', $dir);
        $this->assertSame(ModuleState::Enabled, $this->theme()->state);
    }

    public function test_assets_are_served_safely()
    {
        $dir = $this->makeTheme();
        File::put($dir.'/notes.txt', 'private');
        $this->theme();

        $this->get('/theme-assets/acme/sunny/assets/images/hero.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");

        $this->get('/theme-assets/acme/sunny/theme.json')->assertNotFound();
        $this->get('/theme-assets/acme/sunny/notes.txt')->assertNotFound();
        $this->get('/theme-assets/acme/sunny/assets/../theme.json')->assertNotFound();
        $this->get('/theme-assets/acme/sunny/assets/images/missing.png')->assertNotFound();
        $this->get('/theme-assets/acme/nothere/assets/images/hero.png')->assertNotFound();
    }

    public function test_appearance_admin_requires_permission()
    {
        $this->makeTheme();

        $this->actingAs($this->userWithRole('store-manager'))->get(route('admin.themes.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('store-manager'))->post(route('admin.themes.activate', $this->theme()))->assertForbidden();
        $this->actingAs($this->userWithRole('administrator'))->get(route('admin.themes.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/themes/index')->has('themes', count(glob(base_path('themes').'/*/*/theme.json') ?: []) + 1));
        $this->actingAs($this->userWithRole('administrator'))->get(route('admin.themes.customize', $this->theme()))
            ->assertInertia(fn (Assert $page) => $page->component('admin/themes/customize')->has('sections.home', 2));
    }

    public function test_merchant_images_are_re_encoded_and_usable_in_settings()
    {
        $this->makeTheme();
        $owner = $this->owner();
        $png = storage_path('framework/testing/upload-'.uniqid().'.png');
        $this->writePng($png, 20, 10);
        File::append($png, '<?php system($_GET["c"]); ?>'); // appended payload must not survive

        $this->actingAs($owner)->post(route('admin.themes.uploads.store'), [
            'file' => new UploadedFile($png, 'logo.png', 'image/png', null, true),
        ])->assertSessionHasNoErrors();
        File::delete($png);

        $upload = app(ThemeCustomizations::class)->uploads()[0];
        $stored = (string) Storage::disk(config('ecomodular.media_disk'))->get(substr($upload['value'], 6));
        $this->assertStringNotContainsString('system(', $stored);

        $this->actingAs($owner)->put(route('admin.themes.design', $this->theme()), ['design' => ['logo' => $upload['value']]])->assertSessionHasNoErrors();
    }
}
