<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Themes\Sections\ThemeTemplates;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class ThemeEditorTest extends TestCase
{
    use RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
        $this->installPlatform();
        $this->setUpThemes();
    }

    /**
     * A small sections theme: a hero with slide blocks, a header with
     * announcement blocks and a footer.
     *
     * @param  array<string, mixed>  $schemaOverrides  section type => schema (null removes it)
     * @param  array<string, mixed>  $templateOverrides  "templates/index" etc. => JSON
     */
    private function makeSectionsTheme(array $schemaOverrides = [], array $templateOverrides = [], string $name = 'acme/blocks'): string
    {
        $dir = $this->makeTheme($name, ['capabilities' => ['sections'], 'sections' => null]);
        File::ensureDirectoryExists("{$dir}/sections");
        $schemas = array_filter(array_replace([
            'hero' => [
                'name' => 'Hero',
                'settings' => [
                    ['type' => 'text', 'id' => 'heading', 'label' => 'Heading', 'default' => 'Hello'],
                    ['type' => 'color', 'id' => 'background', 'label' => 'Background', 'default' => '#ffffff'],
                    ['type' => 'image', 'id' => 'image', 'label' => 'Image'],
                    ['type' => 'url', 'id' => 'link', 'label' => 'Link'],
                ],
                'blocks' => [['type' => 'slide', 'name' => 'Slide', 'settings' => [['type' => 'text', 'id' => 'title', 'label' => 'Title']]]],
                'max_blocks' => 3,
                'presets' => [['name' => 'Hero', 'blocks' => [['type' => 'slide']]]],
                'enabled_on' => ['templates' => ['*']],
            ],
            'header' => [
                'name' => 'Header', 'limit' => 1, 'enabled_on' => ['groups' => ['header']],
                'blocks' => [['type' => 'announcement', 'name' => 'Announcement', 'settings' => [['type' => 'text', 'id' => 'text', 'label' => 'Text']]]],
            ],
            'footer' => ['name' => 'Footer', 'enabled_on' => ['groups' => ['footer']]],
        ], $schemaOverrides), fn ($v) => $v !== null);

        foreach ($schemas as $type => $schema) {
            File::put("{$dir}/sections/{$type}.json", json_encode($schema));
        }

        $templates = [
            'templates/index' => ['sections' => ['hero' => [
                'type' => 'hero',
                'settings' => ['heading' => 'Welcome', 'image' => 'asset:assets/images/hero.png', 'link' => '/search'],
                'blocks' => ['s1' => ['type' => 'slide', 'settings' => ['title' => 'First']], 's2' => ['type' => 'slide', 'settings' => ['title' => 'Hidden'], 'disabled' => true]],
                'block_order' => ['s1', 's2'],
            ]], 'order' => ['hero']],
            'groups/header' => ['sections' => ['header' => ['type' => 'header', 'blocks' => ['a1' => ['type' => 'announcement', 'settings' => ['text' => 'Free shipping']]], 'block_order' => ['a1']]], 'order' => ['header']],
            'groups/footer' => ['sections' => ['footer' => ['type' => 'footer']], 'order' => ['footer']],
        ];
        foreach (['product', 'collection', 'search', 'cart', 'page'] as $template) {
            $templates["templates/{$template}"] = ['sections' => [], 'order' => []];
        }

        foreach (array_filter(array_replace($templates, $templateOverrides), fn ($v) => $v !== null) as $path => $data) {
            File::ensureDirectoryExists(dirname("{$dir}/{$path}.json"));
            File::put("{$dir}/{$path}.json", json_encode($data));
        }

        File::ensureDirectoryExists("{$dir}/src/sections");
        foreach (array_keys($schemas) as $type) {
            File::put("{$dir}/src/sections/{$type}.tsx", 'export default function Section() { return null; }');
        }

        return $dir;
    }

    private function sync(string $name = 'acme/blocks'): Extension
    {
        app(ThemeRepository::class)->sync();

        return Extension::query()->where('type', 'theme')->where('name', $name)->firstOrFail();
    }

    private function activate(string $name = 'acme/blocks'): Extension
    {
        $this->sync($name);

        return app(ThemeManager::class)->activate($name);
    }

    private function manifest(string $name = 'acme/blocks'): ThemeManifest
    {
        return app(ThemeManager::class)->renderedManifest($this->sync($name));
    }

    public function test_a_sections_theme_validates_and_renders_every_area_from_its_templates(): void
    {
        $this->makeSectionsTheme();
        $this->activate();

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('storefront.themeSections.template', 'index')
            ->where('storefront.themeSections.header.0.blocks.0.settings.text', 'Free shipping')
            ->where('storefront.themeSections.footer.0.type', 'footer')
            ->where('storefront.themeSections.main.0.settings.heading', 'Welcome')
            // Defaults fill missing settings; images and links are resolved for display.
            ->where('storefront.themeSections.main.0.settings.background', '#ffffff')
            ->where('storefront.themeSections.main.0.settings.link', '/search')
            ->where('storefront.themeSections.main.0.settings.image', fn ($url) => is_string($url) && str_contains($url, 'hero.png'))
            // Hidden blocks are not rendered.
            ->has('storefront.themeSections.main.0.blocks', 1)
            ->where('storefront.themeEditor', false));

        // Pages without a template still get the header and footer.
        $this->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('storefront.themeSections.template', 'cart')
            ->has('storefront.themeSections.main', 0)
            ->has('storefront.themeSections.header', 1));
    }

    public function test_invalid_section_packages_are_refused_with_clear_errors(): void
    {
        $dir = $this->makeSectionsTheme(
            ['hero' => ['name' => 'Hero', 'settings' => [['type' => 'colour', 'id' => 'x', 'label' => 'X']], 'presets' => [['name' => 'Hero', 'blocks' => [['type' => 'nope']]]]]],
            ['templates/cart' => null, 'templates/index' => ['sections' => ['a' => ['type' => 'missing']], 'order' => ['a']]],
        );
        File::delete("{$dir}/src/sections/footer.tsx");

        $theme = $this->sync();
        $errors = implode("\n", (array) $theme->errors);

        $this->assertSame(ModuleState::Invalid, $theme->state);
        $this->assertStringContainsString('sections/hero.json: settings[0] type must be one of', $errors);
        $this->assertStringContainsString('presets[0].blocks[0] uses an undeclared block type', $errors);
        $this->assertStringContainsString('sections/footer.json has no component src/sections/footer.tsx', $errors);
        $this->assertStringContainsString('templates/cart.json is missing', $errors);
        $this->assertStringContainsString('templates/index.json sections.a: uses a section type the theme does not have', $errors);
    }

    public function test_saving_validates_types_places_limits_and_values(): void
    {
        $this->makeSectionsTheme();
        $theme = $this->activate();
        $owner = $this->owner();
        $url = route('admin.themes.editor.save', $theme);

        // A header section cannot go into a page template; a colour must be a colour; max blocks.
        $this->actingAs($owner)->putJson($url, ['templates' => ['template:index' => ['sections' => [
            'h' => ['type' => 'header'],
            'x' => ['type' => 'hero', 'settings' => ['background' => 'red'], 'blocks' => ['1' => ['type' => 'slide'], '2' => ['type' => 'slide'], '3' => ['type' => 'slide'], '4' => ['type' => 'slide']], 'block_order' => ['1', '2', '3', '4']],
        ], 'order' => ['h', 'x']]]])
            ->assertUnprocessable()
            ->assertJson(['errors' => ['template:index' => [
                'sections.h' => '"Header" cannot be added to this page',
                'sections.x.settings.background' => '"Background" must be a color like #1a2b3c',
                'sections.x.blocks' => 'can have at most 3 blocks',
            ]]]);

        // Nothing was stored.
        $this->assertFalse(app(ThemeTemplates::class)->isCustomized($this->manifest(), 'template:index'));

        $this->putJson($url, ['templates' => ['template:index' => ['sections' => [
            'new' => ['type' => 'hero', 'settings' => ['heading' => 'Saved heading'], 'blocks' => [], 'block_order' => []],
        ], 'order' => ['new']]]])->assertOk()->assertJsonPath('templates.template:index.sections.new.settings.heading', 'Saved heading');

        // On the live theme, Save is live at once (as on Shopify).
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('storefront.themeSections.main.0.settings.heading', 'Saved heading'));
    }

    public function test_unsaved_changes_preview_only_for_that_staff_member_in_editor_mode(): void
    {
        $this->makeSectionsTheme();
        $theme = $this->activate();
        $owner = $this->owner();

        $this->actingAs($owner)->putJson(route('admin.themes.editor.draft', $theme), ['templates' => ['template:index' => ['sections' => [
            'hero' => ['type' => 'hero', 'settings' => ['heading' => 'Draft heading']],
        ], 'order' => ['hero']]]])->assertOk();

        // In the editor's frame: the draft, framed by this origin only.
        $this->get('/?_editor=1')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('storefront.themeEditor', true)
                ->where('storefront.themeSections.main.0.settings.heading', 'Draft heading'));

        // Without editor mode, and for shoppers, the saved theme; never framable.
        $this->get('/')->assertHeader('X-Frame-Options', 'DENY')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('storefront.themeSections.main.0.settings.heading', 'Welcome'));
        auth()->logout();
        $this->get('/?_editor=1')->assertHeader('X-Frame-Options', 'DENY')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('storefront.themeEditor', false)
                ->where('storefront.themeSections.main.0.settings.heading', 'Welcome'));
    }

    public function test_the_editor_requires_theme_permission_and_a_sections_theme(): void
    {
        $this->makeSectionsTheme();
        $theme = $this->activate();
        $this->makeTheme('acme/sunny');
        $classic = $this->sync('acme/sunny');

        $this->actingAs($this->userWithRole('analyst'))->get(route('admin.themes.editor', $theme))->assertForbidden();
        $this->actingAs($this->userWithRole('analyst'))->putJson(route('admin.themes.editor.save', $theme), [])->assertForbidden();

        $this->actingAs($this->owner())->get(route('admin.themes.editor', $theme))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/themes/editor')
                ->has('schemas.hero')
                ->where('templates.group:header.order.0', 'header')
                ->where('previews.index', '/?_editor=1'));
        $this->get(route('admin.themes.editor', $classic))->assertRedirect(route('admin.themes.customize', $classic));
    }
}
