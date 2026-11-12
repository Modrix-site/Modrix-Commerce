<?php

namespace Tests\Feature\Storefront;

use App\Core\Settings\SettingsRepository;
use App\Core\Themes\PageSettingFields;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogPageSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);

        // 15 products, priced 10.00 … 24.00, created newest last.
        foreach (range(1, 15) as $i) {
            Product::factory()->withVariant(sprintf('%d.00', 25 - $i))->create(['name' => "Item {$i}", 'published_at' => now()->subMinutes(100 - $i)]);
        }
    }

    private function pageSettings(array $values): void
    {
        app(SettingsRepository::class)->set(PageSettingFields::STORE_KEY, $values);
    }

    public function test_page_size_and_default_sort()
    {
        $this->pageSettings(['catalog.per_page' => 12, 'catalog.default_sort' => 'price_asc']);

        $this->get(route('storefront.search'))->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 12)
            ->where('products.per_page', 12)
            ->where('sort', 'price_asc')
            ->where('products.data.0.price.amount', '10.00'));
    }

    public function test_visitor_sort_wins()
    {
        $this->pageSettings(['catalog.default_sort' => 'price_asc']);

        $this->get(route('storefront.search', ['sort' => 'name']))->assertInertia(fn (Assert $page) => $page->where('sort', 'name'));
    }

    public function test_page_two_keeps_sort_and_page_size()
    {
        $this->pageSettings(['catalog.per_page' => 12]);

        $this->get(route('storefront.search', ['page' => 2, 'sort' => 'price_asc']))->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 3)
            ->where('products.current_page', 2)
            ->where('sort', 'price_asc')
            ->where('products.data.2.price.amount', '24.00'));
    }

    public function test_defaults_are_unchanged()
    {
        $this->get(route('storefront.search'))->assertInertia(fn (Assert $page) => $page
            ->where('products.per_page', 24)
            ->where('sort', 'newest'));
    }
}
