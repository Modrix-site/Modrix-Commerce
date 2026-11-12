<?php

namespace Tests\Feature\Storefront;

use App\Domains\Catalog\CategoryMenu;
use App\Domains\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
    }

    private function category(string $name, ?Category $parent = null, bool $active = true, int $position = 0): Category
    {
        return Category::query()->create([
            'name' => $name, 'slug' => str($name)->slug()->toString(), 'parent_id' => $parent?->id,
            'is_active' => $active, 'position' => $position,
        ]);
    }

    public function test_the_storefront_shares_the_category_tree()
    {
        $electronics = $this->category('Electronics');
        $phones = $this->category('Phones', $electronics, position: 1);
        $this->category('Laptops', $electronics, position: 0);
        $this->category('Android', $phones);
        $this->category('Hidden', $electronics, active: false);
        $this->category('Home');

        $this->get('/search')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.categoryTree.0.name', 'Electronics')
            ->where('storefront.categoryTree.0.url', '/c/electronics')
            ->where('storefront.categoryTree.0.children.0.name', 'Laptops')
            ->where('storefront.categoryTree.0.children.1.name', 'Phones')
            ->where('storefront.categoryTree.0.children.1.children.0.name', 'Android')
            ->has('storefront.categoryTree.0.children', 2)
            ->where('storefront.categoryTree.1.name', 'Home'));
    }

    public function test_the_tree_stops_at_three_levels_and_skips_children_of_inactive_categories()
    {
        $a = $this->category('A');
        $b = $this->category('B', $a);
        $c = $this->category('C', $b);
        $this->category('D', $c);
        $off = $this->category('Off', active: false);
        $this->category('Orphan', $off);

        $tree = app(CategoryMenu::class)->tree();

        $this->assertCount(1, $tree);
        $this->assertSame('C', $tree[0]['children'][0]['children'][0]['name']);
        $this->assertSame([], $tree[0]['children'][0]['children'][0]['children']);
    }
}
