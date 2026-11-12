<?php

namespace Tests\Feature\Storefront;

use App\Core\Localization\ContentTranslations;
use App\Core\Localization\Locales;
use App\Core\Settings\SettingsRepository;
use App\Core\Themes\StoreNavigation;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeRepository;
use App\Core\Themes\ThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class ContentTranslationTest extends TestCase
{
    use RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpThemes();
        $this->makeTheme('acme/translations');
        app(ThemeRepository::class)->sync();
        app(ThemeManager::class)->activate('acme/translations');
        app(Locales::class)->update('en', ['es']);
    }

    public function test_homepage_footer_and_theme_text_follow_the_language_mode(): void
    {
        $theme = app(ThemeResolver::class)->forRequest(Request::create('/'));
        $settings = app(SettingsRepository::class);
        $settings->set($theme->manifest->settingsPrefix().'.design', [
            'announcement' => 'Weekend delivery across the city',
            'footer_text' => 'Made for everyday rooms',
        ]);
        $settings->set($theme->manifest->settingsPrefix().'.sections', [
            'home' => [[
                'type' => 'rich_text',
                'settings' => ['heading' => 'A quiet weekend', 'body' => 'Rooms arranged for slower days.'],
            ]],
        ]);
        $settings->set(StoreNavigation::KEY, [
            'header' => ['title' => 'Store departments', 'enabled' => true, 'links' => [
                ['type' => 'link', 'label' => 'Weekend rooms', 'url' => '/search'],
            ]],
            'footer' => [[
                'title' => 'House notes',
                'enabled' => true,
                'links' => [['type' => 'link', 'label' => 'Delivery promise', 'url' => '/search']],
            ]],
        ]);

        $this->remember('es', 'Weekend delivery across the city', 'Entrega de fin de semana', 'automatic');
        $this->remember('es', 'Made for everyday rooms', 'Hecho para las habitaciones de cada día', 'automatic');
        $this->remember('es', 'A quiet weekend', 'Un fin de semana tranquilo', 'automatic');
        $this->remember('es', 'Rooms arranged for slower days.', 'Habitaciones para días más lentos.', 'automatic');
        $this->remember('es', 'Store departments', 'Departamentos', 'automatic');
        $this->remember('es', 'Weekend rooms', 'Habitaciones de fin de semana', 'automatic');
        $this->remember('es', 'House notes', 'Notas de la casa', 'automatic');
        $this->remember('es', 'Delivery promise', 'Promesa de entrega', 'automatic');

        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.announcement', 'Entrega de fin de semana')
            ->where('storefront.theme.footerText', 'Hecho para las habitaciones de cada día')
            ->where('sections.0.settings.heading', 'Un fin de semana tranquilo')
            ->where('sections.0.settings.body', 'Habitaciones para días más lentos.')
            ->where('storefront.navigation.header.title', 'Departamentos')
            ->where('storefront.navigation.header.links.0.label', 'Habitaciones de fin de semana')
            ->where('storefront.navigation.footer.0.title', 'Notas de la casa')
            ->where('storefront.navigation.footer.0.links.0.label', 'Promesa de entrega'));

        $this->get('/?lang=en')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.announcement', 'Weekend delivery across the city')
            ->where('storefront.theme.footerText', 'Made for everyday rooms'));

        $settings->set(ContentTranslations::KEY, ContentTranslations::MODE_MANUAL);

        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.announcement', 'Weekend delivery across the city')
            ->where('storefront.navigation.footer.0.title', 'House notes'));

        $this->remember('es', 'Weekend delivery across the city', 'Entrega escrita a mano', 'manual');
        $this->remember('es', 'House notes', 'Notas escritas', 'manual');

        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.announcement', 'Entrega escrita a mano')
            ->where('storefront.navigation.footer.0.title', 'Notas escritas')
            ->where('storefront.theme.footerText', 'Made for everyday rooms'));

        $this->actingAs($this->owner())->put(route('admin.settings.languages.update'), [
            'default' => 'en',
            'additional' => ['es'],
            'mode' => 'automatic',
            'translations' => [],
        ])->assertRedirect();

        $this->assertSame(ContentTranslations::MODE_AUTOMATIC, app(ContentTranslations::class)->mode());
        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.announcement', 'Entrega de fin de semana'));

        $this->actingAs($this->owner())->put(route('admin.settings.languages.update'), [
            'default' => 'en',
            'additional' => ['es'],
            'mode' => 'manual',
            'translations' => [
                ['locale' => 'es', 'source' => 'Weekend delivery across the city', 'value' => 'Entrega del comerciante'],
                ['locale' => 'es', 'source' => 'Made for everyday rooms', 'value' => ''],
            ],
        ])->assertRedirect();

        $this->actingAs($this->owner())->get(route('admin.settings.languages.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mode', 'manual')
                ->where('translations.es.Weekend delivery across the city', 'Entrega del comerciante')
                ->has('phrases'));

        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.announcement', 'Entrega del comerciante'));
    }

    private function remember(string $locale, string $source, string $value, string $origin): void
    {
        DB::table('content_translations')->insert([
            'locale' => $locale,
            'source_hash' => hash('sha256', $source),
            'source' => $source,
            'value' => $value,
            'origin' => $origin,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_an_unavailable_translator_is_not_retried_for_every_phrase(): void
    {
        app()->instance('env', 'local');
        app()->setLocale('es');
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Translation provider timed out');
        });
        $translations = app(ContentTranslations::class);
        $this->assertSame('A new baby campaign', $translations->translate('A new baby campaign'));
        $translations->warm(['A second campaign', 'A third campaign']);
        $this->assertSame('Another new phrase', $translations->translate('Another new phrase'));
        $this->assertSame(1, $attempts);
    }

    public function test_repeated_warm_calls_share_one_request_budget(): void
    {
        app()->instance('env', 'local');
        app()->setLocale('es');
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseData' => ['translatedText' => 'Texto traducido']])]);
        $translations = app(ContentTranslations::class);
        $translations->warm(array_map(fn ($i) => 'First campaign phrase '.$i, range(1, 12)));
        $translations->warm(array_map(fn ($i) => 'Second campaign phrase '.$i, range(1, 12)));
        $this->assertSame('Uncached campaign text', $translations->translate('Uncached campaign text'));
        Http::assertSentCount(8);
    }

    public function test_automatic_translation_respects_the_page_deadline(): void
    {
        app()->instance('env', 'local');
        app()->setLocale('es');
        Http::fake(['*' => Http::response(['responseData' => ['translatedText' => 'Texto traducido']])]);
        request()->attributes->set('content_translations_state', [
            'editor' => [], 'stored' => [], 'budget' => 8, 'attempted' => [], 'deadline' => microtime(true) - 1,
        ]);
        $translations = app(ContentTranslations::class);
        $translations->warm(['A campaign past its deadline']);
        $this->assertSame('A second late phrase', $translations->translate('A second late phrase'));
        Http::assertNothingSent();
    }
}
