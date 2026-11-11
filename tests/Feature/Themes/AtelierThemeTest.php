<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\ModuleState;
use App\Core\Themes\Sections\ThemeTemplates;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class AtelierThemeTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpThemes();
        File::copyDirectory(base_path('themes/modrixcommerce/atelier'), $this->themesPath.'/modrixcommerce/atelier');
        app(ThemeRepository::class)->sync();
        app(ThemeManager::class)->activate('modrixcommerce/atelier');
        $this->enableCheckout();
    }

    public function test_package_and_all_native_editor_templates_are_valid(): void
    {
        $theme = app(ThemeRepository::class)->find('modrixcommerce/atelier');
        $this->assertNotNull($theme);
        $this->assertSame(ModuleState::Enabled, $theme->state, implode(' ', (array) $theme->errors));

        $manifest = ThemeManifest::fromArray($theme->manifest);
        $this->assertTrue(ThemeTemplates::supports($manifest));

        foreach (ThemeTemplates::keys() as $key) {
            $saved = app(ThemeTemplates::class)->saved($manifest, $key);
            $this->assertNotEmpty($saved['order'], "Template {$key} must have a non-empty order");
        }

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.themeSections.template', 'index')
            ->where('storefront.themeSections.header.0.settings.brand', 'ATELIER')
            ->where('storefront.themeSections.main.0.type', 'hero')
            ->where('storefront.themeSections.main.0.settings.heading', 'The art of everyday.')
            ->has('storefront.themeSections.main', 8)
        );

        $this->get('/theme-assets/modrixcommerce/atelier/assets/hero.jpg')->assertOk();
    }

    public function test_live_customizer_preview_and_saving(): void
    {
        $theme = app(ThemeRepository::class)->find('modrixcommerce/atelier');
        $manifest = ThemeManifest::fromArray($theme->manifest);
        $template = app(ThemeTemplates::class)->saved($manifest, 'template:index');

        // Customize heading in customizer draft
        $template['sections']['hero']['settings']['heading'] = 'Considered Objects';

        $this->actingAs($this->owner())
            ->get(route('admin.themes.editor', $theme))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/themes/editor')
                ->has('schemas.hero')
                ->has('schemas.editorial')
                ->has('schemas.product-rail')
                ->has('schemas.curated-gifts')
                ->has('templates.template:product')
            );

        $this->putJson(route('admin.themes.editor.draft', $theme), [
            'templates' => ['template:index' => $template],
        ])->assertOk();

        // Draft preview receives customized heading
        $this->get('/?_editor=1')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.themeSections.main.0.settings.heading', 'Considered Objects')
        );

        // Published storefront retains default until saved
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.themeSections.main.0.settings.heading', 'The art of everyday.')
        );
    }

    public function test_catalog_and_search_rendering_with_products(): void
    {
        $this->sellable('25.00', 10, [], ['name' => 'Fountain Pen', 'slug' => 'fountain-pen']);
        $this->sellable('35.00', 10, [], ['name' => 'Linen Notebook', 'slug' => 'linen-notebook']);

        $this->get('/search')->assertInertia(fn (Assert $page) => $page
            ->component('storefront/listing')
            ->where('storefront.themeSections.template', 'search')
            ->where('storefront.themeSections.main.0.type', 'main-collection')
            ->has('products.data', 2)
            ->where('products.total', 2)
        );
    }
}
