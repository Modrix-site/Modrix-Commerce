<?php

namespace Tests\Feature\Catalog;

use App\Core\Themes\Sections\SectionSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTag;
use App\Domains\Catalog\StorefrontCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class ProductTagsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    /**
     * @param  list<string>  $tags
     */
    private function tagged(string $name, array $tags, bool $draft = false): Product
    {
        $product = $this->sellable('20.00', 5, [], ['name' => $name])->product;

        if ($draft) {
            $product->forceFill(['status' => 'draft', 'published_at' => null])->save();
        }

        foreach ($tags as $tag) {
            $product->tags()->create(['tag' => ProductTag::normalise($tag)]);
        }

        return $product;
    }

    public function test_admin_saves_tags_and_shows_them_in_the_product_list(): void
    {
        $product = $this->tagged('Sleep suit', ['sleeping']);
        $this->actingAs($this->userWithRole('catalog-manager'));

        $this->put(route('admin.products.update', $product), [
            'type' => 'simple',
            'name' => 'Sleep suit',
            'status' => 'published',
            'category_ids' => [],
            'tags' => ['Sleeping', ' Baby  Out '],
            'variants' => [[
                'id' => $product->variants()->firstOrFail()->id,
                'sku' => $product->variants()->firstOrFail()->sku,
                'price' => '20.00',
                'track_inventory' => true,
                'allow_backorder' => false,
                'is_active' => true,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['baby out', 'sleeping'], $product->fresh()->tags()->orderBy('tag')->pluck('tag')->all());

        $this->get(route('admin.products.index'))->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.tags', ['baby out', 'sleeping'])
            ->where('tagSuggestions', fn ($tags) => collect($tags)->contains('baby out')));

        $this->get(route('admin.products.index', ['tag' => 'Baby Out']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Sleep suit'));
    }

    public function test_storefront_filters_the_catalog_by_tag(): void
    {
        $this->tagged('Night light', ['sleeping', 'home wares']);
        $this->tagged('Sleep bag', ['Sleeping']);
        $this->tagged('Bib', ['eat & drink']);
        $this->tagged('Hidden blanket', ['sleeping'], draft: true);

        $this->get('/search?tag=sleeping')->assertInertia(fn (Assert $page) => $page
            ->component('storefront/listing')
            ->has('products.data', 2)
            ->where('title', 'Sleeping')
            ->where('tag.name', 'sleeping')
            ->where('tag.url', '/search?tag=sleeping')
            ->where('filters.tag', 'sleeping'));

        // Matched normalised: case and spacing do not matter; & is literal.
        $this->get('/search?tag='.rawurlencode('  Eat   &  Drink '))->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('products.data.0.name', 'Bib')->where('title', 'Eat & Drink'));

        // Tag combines with search.
        $this->get('/search?q=light&tag=sleeping')->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('products.data.0.name', 'Night light'));

        $this->get('/search?tag=unknown')->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->getJson('/search?tag='.str_repeat('x', 61))->assertUnprocessable();
    }

    public function test_store_tags_count_only_visible_products(): void
    {
        $this->tagged('Night light', ['sleeping', 'home wares']);
        $this->tagged('Sleep bag', ['sleeping']);
        $this->tagged('Hidden blanket', ['sleeping', 'secret'], draft: true);

        $this->get('/search')->assertInertia(fn (Assert $page) => $page
            ->where('filterTags.0', ['name' => 'sleeping', 'label' => 'Sleeping', 'slug' => 'sleeping', 'url' => '/search?tag=sleeping', 'count' => 2])
            ->where('filterTags.1.name', 'home wares')
            ->where('filterTags.1.url', '/search?tag=home%20wares')
            ->has('filterTags', 2));

        $catalog = app(StorefrontCatalog::class);
        $this->assertSame(['home wares', 'sleeping'], array_column($catalog->tags(10, ['home wares', 'secret', 'sleeping']), 'name'));
        $this->assertSame(['sleeping'], array_column($catalog->tags(1), 'name'));
        $this->assertSame([], $catalog->tags(10, []));
    }

    public function test_tags_section_setting_is_validated_and_normalised(): void
    {
        $setting = ['type' => 'tags', 'id' => 'tab_tags', 'label' => 'Tabs', 'fallback' => 'popular'];

        $this->assertSame([], SectionSettings::definitionErrors($setting));
        $this->assertSame([['sleeping', 'baby out'], null], SectionSettings::check($setting, [' Sleeping', 'Baby  Out', 'sleeping'], fn () => true));
        $this->assertSame([['toys', 'apparels'], null], SectionSettings::check($setting, 'toys, Apparels', fn () => true));
        $this->assertSame([[], null], SectionSettings::check($setting, null, fn () => true));
        $this->assertNotNull(SectionSettings::check($setting, [1, 2], fn () => true)[1]);
        $this->assertNotNull(SectionSettings::check($setting, [str_repeat('x', 61)], fn () => true)[1]);
        $this->assertSame([], SectionSettings::default($setting));
    }
}
