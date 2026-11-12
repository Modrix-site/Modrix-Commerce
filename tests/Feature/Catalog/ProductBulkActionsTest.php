<?php

namespace Tests\Feature\Catalog;

use App\Core\Authorization\Models\Permission;
use App\Core\Authorization\Models\Role;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class ProductBulkActionsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->actingAs($this->userWithRole('catalog-manager'));
    }

    private function product(string $price = '20.00', array $attributes = [], ?int $stock = 10): Product
    {
        return $this->sellable($price, $stock, product: $attributes)->product;
    }

    /**
     * @param  list<Product>  $products
     */
    private function bulk(string $action, array $products, array $input = []): TestResponse
    {
        return $this->post(route('admin.products.bulk'), ['action' => $action, 'ids' => array_map(fn (Product $p) => $p->id, $products), ...$input]);
    }

    private function price(Product $product, string $currency = 'USD'): string
    {
        return (string) $product->variants()->firstOrFail()->prices()->where('currency', $currency)->value('amount');
    }

    public function test_publish_draft_archive()
    {
        $a = $this->product(attributes: ['status' => ProductStatus::Draft]);
        $unpriced = $this->product(attributes: ['status' => ProductStatus::Draft, 'name' => 'No price']);
        $unpriced->variants()->firstOrFail()->prices()->delete();

        $this->bulk('publish', [$a, $unpriced])->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Updated 1 product. Skipped 1: No price (no active variant with a price).');
        $this->assertSame(ProductStatus::Published, $a->fresh()->status);
        $this->assertSame(ProductStatus::Draft, $unpriced->fresh()->status);

        $this->bulk('draft', [$a])->assertSessionHasNoErrors();
        $this->assertSame(ProductStatus::Draft, $a->fresh()->status);
        $this->bulk('archive', [$a])->assertSessionHasNoErrors();
        $this->assertSame(ProductStatus::Archived, $a->fresh()->status);
    }

    public function test_categories_brand_tags_featured()
    {
        [$a, $b] = [$this->product(), $this->product()];
        $category = Category::query()->create(['name' => 'Mugs', 'slug' => 'mugs']);
        $brand = Brand::query()->create(['name' => 'Northwind', 'slug' => 'northwind']);

        $this->bulk('add_category', [$a, $b], ['category_id' => $category->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $category->products()->count());
        $this->bulk('remove_category', [$a], ['category_id' => $category->id]);
        $this->assertSame([$b->id], $category->products()->pluck('products.id')->all());

        $this->bulk('set_brand', [$a, $b], ['brand_id' => $brand->id]);
        $this->assertSame($brand->id, $b->fresh()->brand_id);
        $this->bulk('set_brand', [$a], ['brand_id' => null]);
        $this->assertNull($a->fresh()->brand_id);

        $this->bulk('feature', [$a]);
        $this->assertTrue($a->fresh()->is_featured);
        $this->bulk('unfeature', [$a]);
        $this->assertFalse($a->fresh()->is_featured);

        // Tags: normalised; a product already at the cap is skipped.
        foreach (range(1, ProductTag::MAX_PER_PRODUCT) as $i) {
            $b->tags()->create(['tag' => "tag {$i}"]);
        }
        $this->bulk('add_tags', [$a, $b], ['tags' => ['  Summer Sale ']])->assertSessionHasNoErrors();
        $this->assertSame(['summer sale'], $a->fresh()->tagNames());
        $this->assertNotContains('summer sale', $b->fresh()->tagNames());
        $this->bulk('remove_tags', [$a], ['tags' => ['SUMMER sale']]);
        $this->assertSame([], $a->fresh()->tagNames());
    }

    public function test_price_increase_and_decrease_percent_and_fixed()
    {
        $p = $this->product('20.00');

        $this->bulk('adjust_prices', [$p], ['direction' => 'increase', 'mode' => 'percent', 'value' => '10'])->assertSessionHasNoErrors();
        $this->assertSame('22.0000', $this->price($p));
        $this->bulk('adjust_prices', [$p], ['direction' => 'decrease', 'mode' => 'fixed', 'value' => '2.5']);
        $this->assertSame('19.5000', $this->price($p));
        $this->bulk('adjust_prices', [$p], ['direction' => 'decrease', 'mode' => 'percent', 'value' => '33.33']);
        $this->assertSame('13.0000', $this->price($p));

        $this->bulk('adjust_prices', [$p], ['direction' => 'decrease', 'mode' => 'percent', 'value' => '100'])->assertSessionHasErrors('value');
        $this->bulk('adjust_prices', [$p], ['direction' => 'increase', 'mode' => 'percent', 'value' => '0'])->assertSessionHasErrors('value');
    }

    public function test_price_decrease_floors_and_lists_products()
    {
        $p = $this->product('3.00', ['name' => 'Cheap mug']);
        $p->variants()->firstOrFail()->prices()->create(['currency' => 'JPY', 'amount' => '300']);

        $this->bulk('adjust_prices', [$p], ['direction' => 'decrease', 'mode' => 'fixed', 'value' => '5'])
            ->assertInertiaFlash('toast.message', 'Updated 1 product. Skipped 1: Cheap mug (price floored at the lowest amount).');

        $this->assertSame('0.0100', $this->price($p));
        // A fixed amount in a zero-decimal currency floors at 1, not 0.01.
        $this->assertSame('295.0000', $this->price($p, 'JPY'));

        $this->bulk('adjust_prices', [$p], ['direction' => 'decrease', 'mode' => 'fixed', 'value' => '1000']);
        $this->assertSame('1.0000', $this->price($p, 'JPY'));
    }

    public function test_set_stock_skips_untracked_and_digital()
    {
        $tracked = $this->product();
        $digital = $this->product(attributes: ['is_digital' => true, 'name' => 'Ebook']);
        $digital->variants()->update(['track_inventory' => false]);

        $this->bulk('set_stock', [$tracked, $digital], ['quantity' => 42])
            ->assertInertiaFlash('toast.message', 'Updated 1 product. Skipped 1: Ebook (stock is not tracked).');
        $this->assertSame(42, $tracked->variants()->firstOrFail()->onHand());
    }

    public function test_delete_soft_deletes_and_keeps_order_history()
    {
        [$a, $b] = [$this->product(), $this->product()];
        $this->actingAs($this->owner());

        $this->bulk('delete', [$a, $b])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
    }

    public function test_export_only_selected()
    {
        $a = $this->product(attributes: ['name' => 'Alpha']);
        $this->product(attributes: ['name' => 'Beta']);

        $csv = $this->bulk('export', [$a])->assertOk()->streamedContent();
        $this->assertStringContainsString('Alpha', $csv);
        $this->assertStringNotContainsString('Beta', $csv);
    }

    public function test_permissions_per_action()
    {
        $p = $this->product();
        // An editor may update products but not delete them.
        $role = Role::query()->create(['name' => 'product-editor', 'display_name' => 'Product editor']);
        $role->permissions()->sync(Permission::query()->whereIn('name', ['products.view', 'products.update'])->pluck('id'));
        $editor = User::factory()->create();
        $editor->roles()->attach($role->id, ['created_at' => now()]);
        $this->actingAs($editor->fresh());

        $this->bulk('publish', [$p])->assertSessionHasNoErrors();
        $this->bulk('delete', [$p])->assertForbidden();
        $this->assertNotSoftDeleted($p);
        $this->actingAs($this->userWithRole('order-manager'));
        $this->bulk('publish', [$p])->assertForbidden();
    }

    public function test_more_than_200_ids_rejected()
    {
        $this->post(route('admin.products.bulk'), ['action' => 'publish', 'ids' => range(1, 201)])->assertSessionHasErrors('ids');
    }
}
