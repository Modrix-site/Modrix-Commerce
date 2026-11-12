<?php

namespace Tests\Feature\Themes;

use App\Core\Themes\StorefrontLinks;
use App\Domains\Catalog\Models\Category;
use App\Domains\Content\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_content_and_active_categories_are_linked(): void
    {
        Page::create(['title' => 'About', 'slug' => 'about', 'content' => 'About us', 'status' => 'published']);
        Page::create(['title' => 'Draft', 'slug' => 'draft', 'content' => 'Draft', 'status' => 'draft']);
        Category::create(['name' => 'Active', 'slug' => 'active', 'is_active' => true]);
        Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $links = new StorefrontLinks;
        $this->assertSame('/pages/about?lang=en#story', $links->resolve('/pages/about?lang=en#story'));
        $this->assertSame('/c/active', $links->resolve('/c/active'));
        $this->assertNull($links->resolve('/pages/draft'));
        $this->assertNull($links->resolve('/pages/missing'));
        $this->assertNull($links->resolve('/c/hidden'));
        $this->assertNull($links->resolve('/c/missing'));
    }

    public function test_unavailable_theme_links_remove_their_labels_without_redirecting_elsewhere(): void
    {
        $content = (new StorefrontLinks)->content([
            'menu_1_label' => 'Our story', 'menu_1_link' => '/pages/missing',
            'button_label' => 'Shop', 'button_link' => '/search',
        ]);
        $this->assertSame('', $content['menu_1_label']);
        $this->assertSame('', $content['menu_1_link']);
        $this->assertSame('/search', $content['button_link']);
        $this->assertSame('Shop', $content['button_label']);
    }

    public function test_external_links_and_other_routes_are_preserved(): void
    {
        $links = new StorefrontLinks;
        foreach (['/account/orders', '/search?q=toy', '#main', 'https://example.com/pages/help'] as $url) {
            $this->assertSame($url, $links->resolve($url));
        }
    }
}
