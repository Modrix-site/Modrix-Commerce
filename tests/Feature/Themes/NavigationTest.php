<?php

namespace Tests\Feature\Themes;

use App\Core\Settings\SettingsRepository;
use App\Core\Themes\StoreNavigation;
use App\Core\Themes\ThemeManager;
use App\Domains\Catalog\Models\Category;
use App\Domains\Content\Models\Page;
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
        app(ThemeManager::class)->activate('modrixcommerce/nord');
        $this->get('/search')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.name', 'modrixcommerce/nord')
            ->where('storefront.navigation.header.links.0.label', 'Browse')
            ->where('storefront.navigation.footer.1.links.0.label', 'Orders'));
        $menus = $this->menus();
        $menus['header']['links'] = [];
        $this->put(route('admin.navigation.update'), $menus)->assertSessionHasNoErrors();
        $this->get('/search')->assertInertia(fn (Assert $page) => $page->has('storefront.navigation.header.links', 0));
    }

    /** Section themes switch from their own demo links once the merchant saves menus. */
    public function test_saved_menus_are_flagged_as_custom_for_section_themes(): void
    {
        $this->get('/search')->assertInertia(fn (Assert $page) => $page->where('storefront.navigation.custom', false));

        $this->actingAs($this->userWithRole('administrator'))
            ->put(route('admin.navigation.update'), $this->menus())->assertSessionHasNoErrors();

        $this->get('/search')->assertInertia(fn (Assert $page) => $page->where('storefront.navigation.custom', true));
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

    public function test_nested_menus_resolve_targets_and_layouts_in_all_placements(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $page = Page::create(['title' => 'Our story', 'slug' => 'story', 'status' => 'published']);
        $category = Category::factory()->create(['is_active' => true]);
        $tree = [['label' => 'Shop', 'url' => '/search', 'children' => [
            ['label' => 'Collection', 'type' => 'category', 'category_id' => $category->id, 'url' => '', 'children' => [
                ['label' => 'Story', 'type' => 'page', 'page_id' => $page->id, 'url' => ''],
            ]],
        ]]];
        $menus = $this->menus();
        $menus['header'] = ['title' => 'Shop', 'layout' => 'horizontal', 'links' => $tree];
        $menus['sidebar'] = ['title' => 'Departments', 'enabled' => true, 'layout' => 'vertical', 'links' => $tree];
        $menus['mobile'] = ['title' => 'Mobile', 'inherit_header' => true, 'enabled' => true, 'links' => []];
        $menus['footer'][0]['links'] = $tree;
        $this->put(route('admin.navigation.update'), $menus)->assertSessionHasNoErrors();
        $page->update(['slug' => 'our-story']);
        $category->update(['slug' => 'new-collection']);
        $this->get('/search')->assertInertia(fn (Assert $view) => $view
            ->where('storefront.navigation.header.layout', 'horizontal')
            ->where('storefront.navigation.sidebar.layout', 'vertical')
            ->where('storefront.navigation.header.links.0.children.0.url', '/c/new-collection')
            ->where('storefront.navigation.header.links.0.children.0.children.0.url', '/pages/our-story')
            ->where('storefront.navigation.mobile.links.0.children.0.url', '/c/new-collection')
            ->where('storefront.navigation.footer.0.links.0.children.0.children.0.url', '/pages/our-story'));
        app(ThemeManager::class)->activate('modrixcommerce/tempo');
        $this->get('/')->assertInertia(fn (Assert $view) => $view->where('storefront.navigation.sidebar.links.0.children.0.url', '/c/new-collection'));
        $category->update(['is_active' => false]);
        $this->get('/search')->assertInertia(fn (Assert $view) => $view->missing('storefront.navigation.header.links.0.children'));
        $this->get(route('admin.navigation.index'))->assertInertia(fn (Assert $view) => $view->where('menus.header.links.0.children.0.category_id', $category->id));
    }

    public function test_deep_unsafe_links_depth_limits_and_unknown_fields_cannot_overwrite_menus(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $original = $this->menus();
        $this->put(route('admin.navigation.update'), $original)->assertSessionHasNoErrors();
        $bad = $original;
        $bad['header']['links'][0]['children'] = [['label' => 'Unsafe', 'url' => 'javascript:alert(1)']];
        $this->put(route('admin.navigation.update'), $bad)->assertSessionHasErrors('header.links.0.children.0.url');
        $bad['header']['links'][0]['children'] = [['label' => 'Unknown', 'url' => '/search', 'html' => '<script>bad</script>']];
        $this->put(route('admin.navigation.update'), $bad)->assertSessionHasErrors('header.links.0.children.0');
        $node = ['label' => 'Too deep', 'url' => '/search'];
        for ($i = 0; $i < 4; $i++) {
            $node = ['label' => 'Level', 'url' => '/search', 'children' => [$node]];
        }
        $bad['header']['links'] = [$node];
        $this->put(route('admin.navigation.update'), $bad)->assertSessionHasErrors('header.links.0.children.0.children.0.children.0.children');
        $this->assertSame($original, app(SettingsRepository::class)->get(StoreNavigation::KEY));
    }

    public function test_independent_mobile_menu_and_disabled_placements_are_respected(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $menus = $this->menus();
        $menus['header']['enabled'] = false;
        $menus['sidebar'] = ['title' => 'Sidebar', 'enabled' => false, 'links' => [['label' => 'Hidden', 'url' => '/search']]];
        $menus['mobile'] = ['title' => 'Mobile', 'enabled' => true, 'inherit_header' => false, 'links' => [['label' => 'Mobile only', 'url' => '/cart']]];
        $menus['footer'][0]['enabled'] = false;
        $this->put(route('admin.navigation.update'), $menus)->assertSessionHasNoErrors();
        $this->get('/search')->assertInertia(fn (Assert $view) => $view
            ->has('storefront.navigation.header.links', 0)
            ->has('storefront.navigation.sidebar.links', 0)
            ->where('storefront.navigation.mobile.links.0.label', 'Mobile only')
            ->has('storefront.navigation.footer', 2));
    }
}
