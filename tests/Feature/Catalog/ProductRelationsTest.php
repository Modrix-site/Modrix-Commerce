<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\ProductRelations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class ProductRelationsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'simple',
            'name' => 'Trail Mug',
            'status' => 'published',
            'category_ids' => [],
            'variants' => [[
                'sku' => 'MUG-TR-1',
                'price' => '20.00',
                'track_inventory' => true,
                'allow_backorder' => false,
                'is_active' => true,
                'initial_stock' => 5,
            ]],
        ], $overrides);
    }

    private function other(string $name = 'Other'): Product
    {
        return $this->sellable('10.00', 5, product: ['name' => $name])->product;
    }

    public function test_editor_saves_related_and_upsells_in_order()
    {
        [$b, $c, $d] = [$this->other('B'), $this->other('C'), $this->other('D')];

        $this->actingAs($this->userWithRole('catalog-manager'))
            ->post(route('admin.products.store'), $this->payload(['related_ids' => [$c->id, $b->id], 'upsell_ids' => [$d->id]]))
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Trail Mug')->firstOrFail();
        $this->assertSame([$c->id, $b->id], $product->relatedProducts()->pluck('products.id')->all());
        $this->assertSame([$d->id], $product->upsellProducts()->pluck('products.id')->all());

        $this->get(route('admin.products.edit', $product))->assertInertia(fn (Assert $page) => $page
            ->where('product.related.0.id', $c->id)
            ->where('product.upsells.0.id', $d->id));
    }

    public function test_self_and_limits_are_rejected()
    {
        $ids = collect(range(1, 13))->map(fn ($i) => $this->other("P{$i}")->id)->all();
        $this->actingAs($this->userWithRole('catalog-manager'));

        $this->post(route('admin.products.store'), $this->payload(['related_ids' => $ids]))->assertSessionHasErrors('related_ids');

        $product = $this->other('Self');
        app(ProductRelations::class)->sync($product, [$product->id, $ids[0], $ids[0]], []);
        $this->assertSame([$ids[0]], $product->relatedProducts()->pluck('products.id')->all());
    }

    public function test_product_page_shows_upsells_then_related_and_hides_invisible()
    {
        $product = $this->other('Main');
        $hidden = $this->other('Hidden');
        $hidden->forceFill(['status' => ProductStatus::Draft])->save();
        [$related, $upsell] = [$this->other('Related'), $this->other('Upsell')];
        app(ProductRelations::class)->sync($product, [$hidden->id, $related->id], [$upsell->id]);

        $this->get(route('storefront.product', $product->slug))->assertInertia(fn (Assert $page) => $page
            ->has('related', 1)
            ->where('related.0.id', $related->id)
            ->where('upsells.0.id', $upsell->id));
    }

    public function test_related_falls_back_to_category_when_none_chosen()
    {
        $category = Category::query()->create(['name' => 'Mugs', 'slug' => 'mugs']);
        $product = $this->other('Main');
        $sibling = $this->other('Sibling');
        $product->categories()->attach($category);
        $sibling->categories()->attach($category);

        $this->get(route('storefront.product', $product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('upsells', [])
            ->where('related', fn ($related) => collect($related)->pluck('id')->contains($sibling->id)
                // The product being viewed is never suggested to itself.
                && ! collect($related)->pluck('id')->contains($product->id)));
    }
}
