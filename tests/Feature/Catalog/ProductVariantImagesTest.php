<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Actions\SaveAttribute;
use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductVariantImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    private function image(Product $product): ProductImage
    {
        return $product->images()->create(['disk' => 'public', 'path' => "products/{$product->id}/photo.png", 'alt' => 'Variant photo', 'position' => 0]);
    }

    /** @return array<string, mixed> */
    private function payload(Product $product, ProductVariant $variant, array $fields = []): array
    {
        return ['name' => $product->name, 'type' => 'simple', 'status' => 'published', 'variants' => [array_merge(['id' => $variant->id, 'sku' => $variant->sku, 'price' => '20.00'], $fields)]];
    }

    public function test_variant_image_saves_and_is_exposed_to_the_editor_and_storefront(): void
    {
        $product = Product::factory()->withVariant()->create();
        $variant = $product->variants->firstOrFail();
        $image = $this->image($product);

        $this->actingAs($this->userWithRole('catalog-manager'))->put(route('admin.products.update', $product), $this->payload($product, $variant, ['image_id' => $image->id]))->assertSessionHasNoErrors();
        $this->assertSame($image->id, $variant->refresh()->image_id);
        $this->get(route('admin.products.edit', $product))->assertInertia(fn (Assert $page) => $page->where('product.variants.0.image_id', $image->id)->has('inventoryReasons', 3));
        $this->get(route('storefront.product', $product->slug))->assertInertia(fn (Assert $page) => $page->where('product.variants.0.image_id', $image->id));

        // CSV/API updates that omit the selection must leave it intact.
        $this->put(route('admin.products.update', $product), $this->payload($product, $variant))->assertSessionHasNoErrors();
        $this->assertSame($image->id, $variant->refresh()->image_id);
        $this->put(route('admin.products.update', $product), $this->payload($product, $variant, ['image_id' => null]))->assertSessionHasNoErrors();
        $this->assertNull($variant->refresh()->image_id);
    }

    public function test_image_from_another_product_is_rejected_without_changing_the_variant(): void
    {
        $product = Product::factory()->withVariant()->create();
        $variant = $product->variants->firstOrFail();
        $otherImage = $this->image(Product::factory()->create());
        $this->actingAs($this->owner())->put(route('admin.products.update', $product), $this->payload($product, $variant, ['image_id' => $otherImage->id]))->assertSessionHasErrors('variants.0.image_id');
        $this->assertNull($variant->refresh()->image_id);
    }

    public function test_direct_save_also_rejects_a_foreign_image_and_rolls_back_product_changes(): void
    {
        $product = Product::factory()->withVariant()->create();
        $variant = $product->variants->firstOrFail();
        $image = $this->image(Product::factory()->create());
        $originalName = $product->name;
        $payload = $this->payload($product, $variant, ['image_id' => $image->id]);
        $payload['name'] = 'Must not persist';
        try {
            app(SaveProduct::class)->handle($product, $payload, 'USD');
            $this->fail('A foreign product image was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variants.0.image_id', $exception->errors());
        }
        $this->assertSame($originalName, $product->refresh()->name);
    }

    public function test_each_variant_can_have_a_different_image_or_share_a_product_image(): void
    {
        $attribute = app(SaveAttribute::class)->handle(null, ['name' => 'Color', 'values' => [['value' => 'Red'], ['value' => 'Blue']]]);
        $product = Product::factory()->create(['type' => 'variable']);
        $red = $this->image($product);
        $blue = $this->image($product);
        $values = $attribute->values->pluck('id')->all();
        $data = ['name' => $product->name, 'type' => 'variable', 'status' => 'published', 'option_attribute_ids' => [$attribute->id], 'variants' => [
            ['sku' => 'PHOTO-RED', 'price' => '20', 'image_id' => $red->id, 'option_value_ids' => [$values[0]]],
            ['sku' => 'PHOTO-BLUE', 'price' => '20', 'image_id' => $blue->id, 'option_value_ids' => [$values[1]]],
        ]];
        $this->actingAs($this->owner())->put(route('admin.products.update', $product), $data)->assertSessionHasNoErrors();
        $variants = $product->variants()->get();
        $this->assertSame([$red->id, $blue->id], $variants->pluck('image_id')->all());
        foreach ($data['variants'] as $index => &$row) {
            $row['id'] = $variants[$index]->id;
            $row['image_id'] = $red->id;
        }
        unset($row);
        $this->put(route('admin.products.update', $product), $data)->assertSessionHasNoErrors();
        $red->delete();
        $this->assertSame([null, null], $product->variants()->get()->pluck('image_id')->all());
    }

    public function test_inventory_can_be_added_from_a_product_and_survives_later_product_saves(): void
    {
        $product = Product::factory()->withVariant('20', 4)->create();
        $variant = $product->variants->firstOrFail();
        $editor = route('admin.products.edit', $product);
        $manager = $this->userWithRole('catalog-manager');
        $this->actingAs($manager)->from($editor)->post(route('admin.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 6, 'reason' => 'received', 'note' => 'Received from product editor'])->assertSessionHasNoErrors()->assertRedirect($editor);
        $this->get($editor)->assertInertia(fn (Assert $page) => $page->where('product.variants.0.on_hand', 10));
        $this->put(route('admin.products.update', $product), $this->payload($product, $variant))->assertSessionHasNoErrors();
        $this->assertSame(10, (int) $variant->stocks()->sum('on_hand'));
        $this->assertDatabaseHas('inventory_movements', ['variant_id' => $variant->id, 'quantity_change' => 6, 'reason' => 'received', 'actor_id' => $manager->id]);
        $this->actingAs($this->userWithRole('order-manager'))->from($editor)->post(route('admin.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 1, 'reason' => 'received'])->assertForbidden();
    }
}
