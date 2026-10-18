<?php

namespace Tests\Feature\Themes;

use App\Core\Settings\SettingsRepository;
use App\Core\Themes\StoreNavigation;
use App\Core\Themes\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        $this->installPlatform();
    }

    /** @return array<string,mixed> */
    private function menus(): array
    {
        return ['header' => ['title' => 'Main menu', 'links' => [['label' => 'Browse', 'url' => '/search'], ['label' => 'Account', 'url' => '/account']]], 'footer' => [
            ['title' => 'Shop', 'links' => [['label' => 'Catalog', 'url' => '/search']]],
            ['title' => 'Account', 'links' => [['label' => 'Orders', 'url' => '/account/orders']]],
            ['title' => 'Help', 'links' => []],
        ]];
    }

    public function test_saved_menus_are_shared_with_storefront_in_order(): void
    {
        $this->actingAs($this->userWithRole('administrator'))->get(route('admin.navigation.index'))->assertOk();
        $this->put(route('admin.navigation.update'), $this->menus())->assertSessionHasNoErrors();
        $this->get('/search')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.navigation.header.links.0.label', 'Browse')
            ->where('storefront.navigation.header.links.1.url', '/account')
            ->where('storefront.navigation.footer.1.links.0.label', 'Orders'));
        app(ThemeManager::class)->activate('ecomodular/bloom');
        $this->get('/search')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.name', 'ecomodular/bloom')
            ->where('storefront.navigation.header.links.0.label', 'Browse')
            ->where('storefront.navigation.footer.1.links.0.label', 'Orders'));
        $menus = $this->menus();
        $menus['header']['links'] = [];
        $this->put(route('admin.navigation.update'), $menus)->assertSessionHasNoErrors();
        $this->get('/search')->assertInertia(fn (Assert $page) => $page->has('storefront.navigation.header.links', 0));
    }

    public function test_unsafe_links_are_rejected_without_changing_saved_menus(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $this->put(route('admin.navigation.update'), $this->menus())->assertSessionHasNoErrors();
        foreach (['javascript:alert(1)', '//evil.example', 'http://insecure.example', 'data:text/html,bad'] as $url) {
            $bad = $this->menus();
            $bad['header']['links'][0]['url'] = $url;
            $this->put(route('admin.navigation.update'), $bad)->assertSessionHasErrors('header.links.0.url');
        }
        $this->assertSame($this->menus(), app(SettingsRepository::class)->get(StoreNavigation::KEY));
    }

    public function test_navigation_requires_theme_management_permission(): void
    {
        $this->actingAs($this->userWithRole('store-manager'))->get(route('admin.navigation.index'))->assertForbidden();
        $this->put(route('admin.navigation.update'), $this->menus())->assertForbidden();
    }
}
