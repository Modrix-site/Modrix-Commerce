<?php

namespace Tests\Feature\Storefront;

use App\Core\Settings\StoreSettings;
use App\Core\Themes\ThemeSchema;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTag;
use App\Domains\Catalog\StorefrontCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class BrandAndTagsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    private function product(string $name, array $attributes = [], array $tags = []): Product
    {
        $product = $this->sellable('20.00', 5, product: ['name' => $name, ...$attributes])->product;
        $product->variants()->update(['cost' => '7.0000']);
        foreach ($tags as $tag) {
            ProductTag::query()->insert(['product_id' => $product->id, 'tag' => $tag]);
        }

        return $product;
    }

    public function test_the_brand_page_lists_only_visible_products_of_the_brand()
    {
        $brand = Brand::query()->create(['name' => 'Northwind', 'slug' => 'northwind', 'description' => 'Since 1999.']);
        $this->product('Visible Mug', ['brand_id' => $brand->id]);
        $this->product('Draft Mug', ['brand_id' => $brand->id, 'status' => 'draft']);
        $this->product('Other Mug');

        $this->get(route('storefront.brand', 'northwind'))->assertInertia(fn (Assert $page) => $page
            ->component('storefront/listing')
            ->where('title', 'Northwind')
            ->where('description', 'Since 1999.')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Visible Mug')
            ->missing('products.data.0.cost'));

        $this->get(route('storefront.brand', 'nope'))->assertNotFound();
    }

    public function test_the_product_page_shows_the_brand_and_never_the_cost()
    {
        $brand = Brand::query()->create(['name' => 'Northwind', 'slug' => 'northwind']);
        $product = $this->product('Trail Mug', ['brand_id' => $brand->id]);

        $response = $this->get(route('storefront.product', $product->slug));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('product.brand.name', 'Northwind')
            ->where('product.brand.slug', 'northwind'));
        $this->assertStringNotContainsString('"cost"', json_encode($response->inertiaProps()) ?: '');
    }

    public function test_search_matches_tags_literally()
    {
        $this->product('Enamel Cup', [], ['camping']);
        $this->product('Sale Cup', [], ['50%_off']);
        $this->product('Plain Cup', [], ['50x off']);

        $names = fn (string $q) => collect($this->get(route('storefront.search', ['q' => $q]))->inertiaProps('products.data'))->pluck('name')->sort()->values()->all();

        $this->assertSame(['Enamel Cup'], $names('Camping'));
        $this->assertSame(['Sale Cup'], $names('50%_off'));
    }

    public function test_home_can_show_featured_products()
    {
        $this->product('Featured Lamp', ['is_featured' => true]);
        $this->product('Normal Lamp');

        $cards = app(StorefrontCatalog::class)->featured($this->storeCurrency(), 4);

        $this->assertSame(['Featured Lamp'], array_column($cards, 'name'));
        $this->assertContains('featured', ThemeSchema::sectionTypes()['featured_products']['fields']['source']['options']);
    }

    private function storeCurrency(): string
    {
        return app(StoreSettings::class)->currency();
    }
}
