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
        $page = Page::query()->where('slug', 'about-us')->firstOrFail();
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

    public function test_page_content_keeps_formatting_and_drops_unsafe_html(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $this->post('/admin/pages', [
            'title' => 'Care guide',
            'slug' => 'care-guide',
            'status' => 'published',
            'content' => '<h2>Care</h2><p>Wash <strong>gently</strong>.</p><script>alert(1)</script><p onclick="alert(1)">Safe</p>',
        ])->assertSessionHasNoErrors();

        $stored = (string) Page::query()->where('slug', 'care-guide')->firstOrFail()->content;
        $this->assertStringContainsString('<h2>Care</h2>', $stored);
        $this->assertStringContainsString('<strong>gently</strong>', $stored);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onclick', $stored);

        $this->get('/pages/care-guide')->assertInertia(fn (Assert $p) => $p
            ->where('page.content_html', fn (string $html) => str_contains($html, '<h2>Care</h2>') && ! str_contains($html, 'script'))
            ->where('page.content', fn (string $text) => str_contains($text, 'Care') && ! str_contains($text, '<')));
    }

    public function test_faq_pages_are_edited_in_page_management_and_linked_in_the_footer(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $faq = Page::query()->where('slug', 'faqs')->firstOrFail();
        $this->assertSame(Page::KIND_FAQ, $faq->kind);

        $this->put('/admin/pages/'.$faq->id, [
            'title' => 'FAQs',
            'slug' => 'faqs',
            'kind' => 'faq',
            'status' => 'published',
            'content' => '<p>Intro</p>',
            'faqs' => [
                ['question' => 'Where is my parcel?', 'answer' => '<p>Check <strong>your account</strong>.</p><script>alert(1)</script>'],
                ['question' => '', 'answer' => '<p>Ignored</p>'],
            ],
        ])->assertSessionHasNoErrors();

        $faq->refresh();
        $this->assertCount(1, $faq->faqs);
        $this->assertStringNotContainsString('script', (string) $faq->faqs[0]['answer']);

        $this->get('/pages/faqs')->assertInertia(fn (Assert $page) => $page
            ->where('page.kind', 'faq')
            ->where('page.faqs.0.question', 'Where is my parcel?')
            ->where('page.faqs.0.answer_html', fn (string $html) => str_contains($html, '<strong>your account</strong>') && ! str_contains($html, 'script'))
            ->has('page.faqs', 1));

        $linked = fn (mixed $footer) => collect($footer)->contains(fn (array $column) => collect($column['links'] ?? [])->contains(fn (array $link) => ($link['url'] ?? '') === '/pages/faqs'));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.navigation.footer', $linked));

        $faq->update(['status' => 'draft']);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.navigation.footer', fn (mixed $footer) => ! $linked($footer)));
        $this->get('/pages/faqs')->assertNotFound();
    }

    public function test_a_page_can_be_duplicated_with_its_questions(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $faq = Page::query()->where('slug', 'faqs')->firstOrFail();

        $this->post('/admin/pages/'.$faq->id.'/duplicate')->assertRedirect();

        $copy = Page::query()->where('slug', 'faqs-copy')->firstOrFail();
        $this->assertSame('FAQs copy', $copy->title);
        $this->assertSame(Page::KIND_FAQ, $copy->kind);
        $this->assertSame('draft', $copy->status);
        $this->assertSame($faq->faqs, $copy->faqs);
        $this->assertNotSame($faq->id, $copy->id);

        $this->post('/admin/pages/'.$faq->id.'/duplicate')->assertRedirect();
        $this->assertNotNull(Page::query()->where('slug', 'faqs-copy-2')->first());
    }

    public function test_pages_require_permission(): void
    {
        $this->actingAs($this->userWithRole('store-manager'))->get('/admin/pages')->assertForbidden();
        $this->post('/admin/pages', ['title' => 'No', 'slug' => 'no', 'status' => 'published'])->assertForbidden();
    }
}
