<?php

namespace Tests\Feature\Themes;

use App\Domains\Catalog\Models\Category;
use App\Domains\Content\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        $this->installPlatform();
    }

    public function test_pages_publish_delete_restore_and_validate(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $data = ['title' => 'About us', 'slug' => 'about-us', 'content' => 'Our store story', 'status' => 'draft'];
        $this->post('/admin/pages', $data)->assertSessionHasNoErrors();
        $page = Page::firstOrFail();
        $this->get('/pages/about-us')->assertNotFound();
        $this->put('/admin/pages/'.$page->id, [...$data, 'status' => 'published'])->assertSessionHasNoErrors();
        $this->get('/pages/about-us')->assertInertia(fn (Assert $p) => $p->component('storefront/page')->where('page.content', 'Our store story'));
        $this->post('/admin/pages', $data)->assertSessionHasErrors('slug');
        $this->delete('/admin/pages/'.$page->id)->assertRedirect('/admin/pages');
        $this->get('/pages/about-us')->assertNotFound();
        $this->post('/admin/pages/'.$page->id.'/restore')->assertSessionHasNoErrors();
        $this->get('/pages/about-us')->assertOk();
    }

    public function test_navigation_resolves_current_targets_and_hides_unpublished_destinations(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $page = Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published']);
        $category = Category::factory()->create(['is_active' => true]);
        $menus = ['header' => ['title' => 'Main', 'links' => [['label' => 'About', 'type' => 'page', 'page_id' => $page->id, 'url' => ''], ['label' => 'Shop', 'type' => 'category', 'category_id' => $category->id, 'url' => '']]], 'footer' => [['title' => 'One', 'links' => []], ['title' => 'Two', 'links' => []], ['title' => 'Three', 'links' => []]]];
        $this->put('/admin/navigation', $menus)->assertSessionHasNoErrors();
        $page->update(['slug' => 'our-story']);
        $this->get('/search')->assertInertia(fn (Assert $p) => $p->where('storefront.navigation.header.links.0.url', '/pages/our-story')->where('storefront.navigation.header.links.1.url', '/c/'.$category->slug));
        $page->update(['status' => 'draft']);
        $category->update(['is_active' => false]);
        $this->get('/search')->assertInertia(fn (Assert $p) => $p->has('storefront.navigation.header.links', 0));
        $this->get('/admin/navigation')->assertInertia(fn (Assert $p) => $p->where('menus.header.links.0.page_id', $page->id));
        $menus['header']['links'][0]['page_id'] = 999999;
        $this->put('/admin/navigation', $menus)->assertSessionHasErrors('header.links.0.page_id');
    }

    public function test_pages_require_permission(): void
    {
        $this->actingAs($this->userWithRole('store-manager'))->get('/admin/pages')->assertForbidden();
        $this->post('/admin/pages', ['title' => 'No', 'slug' => 'no', 'status' => 'published'])->assertForbidden();
    }
}
