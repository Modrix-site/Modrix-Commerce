<?php

namespace Tests\Feature\Themes;

use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class PageSettingsStorageTest extends TestCase
{
    use RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpThemes();
        $this->makeTheme('acme/sunny');
        $this->makeTheme('acme/moon', ['design' => ['color.primary' => '#003366']]);
        app(ThemeRepository::class)->sync();
        app(ThemeManager::class)->activate('acme/sunny');
    }

    public function test_page_settings_survive_theme_switch()
    {
        app(ThemeManager::class)->customizeDesign('acme/sunny', ['catalog.per_page' => 12, 'color.primary' => '#112233']);

        app(ThemeManager::class)->activate('acme/moon');

        // Page settings follow the store; colours stay with the theme they were set on.
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.pages.catalog.per_page', 12)
            ->where('storefront.theme.name', 'acme/moon')
            ->where('storefront.theme.css', fn (string $css) => str_contains($css, '#003366') && ! str_contains($css, '#112233')));
    }

    public function test_page_settings_reach_the_storefront()
    {
        app(ThemeManager::class)->customizeDesign('acme/sunny', ['checkout.phone' => 'required']);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.pages.checkout.phone', 'required')
            ->where('storefront.theme.pages.catalog.per_page', 24)
            ->where('storefront.theme.pages.header.search', null));
    }

    public function test_reset_keeps_page_settings()
    {
        app(ThemeManager::class)->customizeDesign('acme/sunny', ['catalog.per_page' => 16, 'color.primary' => '#112233']);
        app(ThemeManager::class)->reset('acme/sunny', 'acme/sunny');

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('storefront.theme.pages.catalog.per_page', 16));
    }
}
