<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Themes\ResolvedTheme;
use App\Core\Themes\ThemeCustomizations;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use App\Core\Themes\ThemeSchema;
use App\Core\Themes\ThemeStyleFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class ThemeStyleFieldsTest extends TestCase
{
    use RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpThemes();
    }

    /**
     * @param  array<string, string>  $style
     */
    private function css(array $style): string
    {
        $manifest = ThemeManifest::fromArray(['name' => 'acme/test', 'display_name' => 'Test', 'version' => '1.0.0', 'platform_version' => '*']);

        return (new ResolvedTheme($manifest, ResolvedTheme::SOURCE_BUILTIN, $style + ThemeSchema::builtinDesign(), app(ThemeCustomizations::class), hasPackage: false))->css();
    }

    public function test_every_area_has_the_full_set_of_fields()
    {
        $fields = ThemeStyleFields::fields();

        $this->assertCount(count(ThemeStyleFields::AREAS) * 16, $fields);
        $this->assertSame('color', $fields['style.catalog.button_bg']['type']);
        $this->assertSame('font', $fields['style.general.heading_font']['type']);
        $this->assertContains('48', $fields['style.product.heading_size']['options']);
        $this->assertSame('Fonts and buttons', $fields['style.cart.price_color']['group']);
        $this->assertArrayHasKey('style.home.button_radius', ThemeSchema::designFields());
    }

    public function test_no_style_values_emit_no_rules()
    {
        $this->assertSame('', ThemeStyleFields::css(ThemeSchema::builtinDesign()));
        $this->assertStringNotContainsString('store-button', $this->css([]));
    }

    public function test_general_values_apply_store_wide()
    {
        $css = $this->css(['style.general.button_bg' => '#112233', 'style.general.heading_font' => 'serif', 'style.general.heading_size' => '40']);

        $this->assertStringContainsString('.storefront .store-button', $css);
        $this->assertStringContainsString('.storefront .hearth-primary', $css);
        $this->assertStringContainsString('background:#112233!important', $css);
        $this->assertStringContainsString('font-size:40px!important', $css);
        $this->assertStringContainsString('font-size:calc(40px * 0.75)!important', $css);
        $this->assertStringContainsString(ThemeSchema::FONT_STACKS['serif'], $css);
    }

    public function test_page_values_are_scoped_and_follow_general()
    {
        $css = $this->css(['style.general.button_bg' => '#111111', 'style.catalog.button_bg' => '#222222', 'style.product.price_sale_color' => '#cc0000']);

        $general = strpos($css, 'background:#111111');
        $catalog = strpos($css, '.storefront[data-page="catalog"] .store-button');
        $this->assertNotFalse($general);
        $this->assertNotFalse($catalog);
        $this->assertGreaterThan($general, $catalog);
        $this->assertStringContainsString('background:#222222!important', $css);
        $this->assertStringContainsString('.storefront[data-page="product"] .store-price-sale{color:#cc0000!important}', $css);
        $this->assertStringNotContainsString('data-page="home"', $css);
    }

    public function test_hover_text_and_radius_are_written()
    {
        $css = $this->css(['style.cart.button_hover_bg' => '#333333', 'style.cart.button_text' => '#ffffff', 'style.cart.button_radius' => '1rem', 'style.cart.button_size' => '16']);

        $this->assertStringContainsString('.storefront[data-page="cart"] .store-button:hover', $css);
        $this->assertStringContainsString('background:#333333!important', $css);
        $this->assertStringContainsString('color:#ffffff!important', $css);
        $this->assertStringContainsString('border-radius:1rem!important', $css);
        $this->assertStringContainsString('font-size:16px!important', $css);
    }

    public function test_page_rules_keep_the_store_wide_values_they_do_not_override()
    {
        $css = $this->css([
            'style.general.price_sale_color' => '#cc0000',
            'style.general.button_hover_bg' => '#333333',
            'style.catalog.price_color' => '#000000',
            'style.catalog.button_bg' => '#123456',
        ]);

        // The catalog scope (more specific than General) repeats General's sale and hover colours.
        $this->assertStringContainsString('.storefront[data-page="catalog"] .store-price-sale{color:#cc0000!important}', $css);
        $this->assertStringContainsString('.storefront[data-page="catalog"] .store-button:hover', $css);
        $catalogHover = substr($css, strpos($css, '.storefront[data-page="catalog"] .store-button:hover'));
        $this->assertStringContainsString('background:#333333!important', $catalogHover);
        // Pages that set nothing get no rules of their own.
        $this->assertStringNotContainsString('data-page="product"', $css);
    }

    public function test_a_button_background_without_hover_still_reacts_on_hover()
    {
        $css = $this->css(['style.product.button_bg' => '#123456']);

        $this->assertStringContainsString('.storefront[data-page="product"] .store-button:hover', $css);
        $this->assertStringContainsString('filter:brightness(0.92)!important', $css);
    }

    public function test_card_prices_of_the_bundled_themes_follow_price_settings()
    {
        $css = $this->css(['style.catalog.price_color' => '#000000']);

        $this->assertStringContainsString('.storefront[data-page="catalog"] .hearth-card-info strong', $css);
        $this->assertStringContainsString('.storefront[data-page="catalog"] .vellaro-card-info strong', $css);
    }

    public function test_invalid_style_values_are_rejected()
    {
        $this->makeTheme();
        app(ThemeRepository::class)->sync();
        $theme = Extension::query()->where('type', 'theme')->where('name', 'acme/sunny')->firstOrFail();

        $this->actingAs($this->userWithRole('administrator'))
            ->put(route('admin.themes.design', $theme), ['design' => [
                'style.catalog.button_bg' => 'red;}body{display:none',
                'style.catalog.heading_size' => '999',
                'style.catalog.body_font' => 'Comic Sans',
            ]])
            ->assertSessionHasErrors(['style.catalog.button_bg', 'style.catalog.heading_size', 'style.catalog.body_font']);

        $this->put(route('admin.themes.design', $theme), ['design' => ['style.catalog.button_bg' => '#123456']])->assertSessionHasNoErrors();
    }
}
