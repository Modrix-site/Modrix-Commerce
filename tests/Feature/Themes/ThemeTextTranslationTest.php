<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Localization\ContentTranslations;
use App\Core\Localization\Locales;
use App\Core\Settings\SettingsRepository;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class ThemeTextTranslationTest extends TestCase
{
    use RefreshDatabase, ThemeFixtures;

    private Extension $theme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpThemes();
        $this->withoutVite();
        Http::preventStrayRequests();
        app(Locales::class)->update('en', ['es', 'ar']);
        $this->makeTheme(overrides: ['customizer' => ['fields' => [
            'custom.about_heading' => ['type' => 'string', 'label' => 'About heading', 'default' => 'About our store', 'max' => 100],
            'custom.about_text' => ['type' => 'text', 'label' => 'About text', 'default' => 'Find your everyday favorites here.', 'max' => 1000],
            'custom.contact_email' => ['type' => 'string', 'label' => 'Email', 'default' => 'store@example.com', 'max' => 100],
            'custom.map_link_label' => ['type' => 'string', 'label' => 'Map link label', 'default' => 'Find our shop', 'max' => 100],
        ]]]);
        app(ThemeRepository::class)->sync();
        $this->theme = Extension::query()->where('name', 'acme/sunny')->firstOrFail();
        app(ThemeManager::class)->activate($this->theme->name);
        $this->theme->refresh();
    }

    private function payload(array $changes = []): array
    {
        return $changes + ['operation' => 'save', 'locale' => 'es', 'key' => 'custom.about_heading', 'source' => 'About our store', 'value' => 'Sobre nuestra tienda'];
    }

    private function url(): string
    {
        return route('admin.themes.text-translations', $this->theme);
    }

    public function test_customizer_exposes_enabled_languages_and_only_frontend_text_fields(): void
    {
        $this->actingAs($this->owner())->get(route('admin.themes.customize', $this->theme))
            ->assertInertia(fn (Assert $page) => $page
                ->where('textTranslation.sourceLocale', 'en')
                ->where('textTranslation.aiAvailable', false)
                ->where('textTranslation.locales.0.code', 'es')
                ->where('design.fields', fn ($fields) => collect($fields)->firstWhere('key', 'custom.about_heading')['translatable']
                    && ! collect($fields)->firstWhere('key', 'custom.contact_email')['translatable']
                    && collect($fields)->firstWhere('key', 'custom.map_link_label')['translatable']));
    }

    public function test_saved_custom_text_follows_the_shopper_language_in_both_modes(): void
    {
        $this->actingAs($this->owner())->postJson($this->url(), $this->payload())->assertOk()->assertJsonPath('value', 'Sobre nuestra tienda');
        foreach (['automatic', 'manual'] as $mode) {
            app(SettingsRepository::class)->set(ContentTranslations::KEY, $mode);
            $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.settings.about_heading', 'Sobre nuestra tienda'));
        }
        $this->get('/?lang=en')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.settings.about_heading', 'About our store'));
        $this->get('/?lang=ar')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.settings.about_heading', 'About our store'));
        app(ThemeManager::class)->customizeDesign($this->theme->name, ['custom.about_heading' => 'Our new story']);
        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.settings.about_heading', 'Our new story'));
    }

    public function test_translations_can_be_read_cleared_and_used_for_home_sections(): void
    {
        $this->actingAs($this->owner())->postJson($this->url(), $this->payload())->assertOk();
        $this->postJson($this->url(), $this->payload(['operation' => 'read']))->assertJsonPath('value', 'Sobre nuestra tienda');
        $this->postJson($this->url(), $this->payload(['value' => '']))->assertJsonPath('value', '');
        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.settings.about_heading', 'About our store'));
        $this->postJson($this->url(), $this->payload(['key' => 'heading', 'section_type' => 'hero', 'source' => 'Sunny days', 'value' => 'Días soleados']))->assertOk();
        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page->where('sections.0.settings.heading', 'Días soleados'));
    }

    public function test_custom_checkout_wording_uses_editor_translations_before_the_interface_catalogue(): void
    {
        $this->actingAs($this->owner())->postJson($this->url(), $this->payload(['key' => 'checkout.heading', 'source' => 'Checkout', 'value' => 'Finalizar mi compra']))->assertOk();
        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.pages.checkout.heading', 'Finalizar mi compra'));
    }

    public function test_long_custom_text_and_labels_are_translated_but_contact_details_are_rejected(): void
    {
        $source = str_repeat('A story about our shop. ', 30);
        app(ThemeManager::class)->customizeDesign($this->theme->name, ['custom.about_text' => $source]);
        $this->actingAs($this->owner())->postJson($this->url(), $this->payload(['key' => 'custom.about_text', 'source' => $source, 'value' => 'Nuestra historia.']))->assertOk();
        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page->where('storefront.theme.settings.about_text', 'Nuestra historia.'));
        foreach ([['key' => 'custom.contact_email', 'source' => 'store@example.com'], ['key' => 'color.primary', 'source' => '#123456'], ['locale' => 'fr'], ['locale' => 'en']] as $invalid) {
            $this->postJson($this->url(), $this->payload($invalid))->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_ai_unavailable_and_unauthorized_requests_do_not_call_a_provider(): void
    {
        $this->actingAs($this->owner())->postJson($this->url(), $this->payload(['operation' => 'draft']))->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson($this->url(), $this->payload())->assertForbidden();
        Http::assertNothingSent();
    }
}
