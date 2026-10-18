<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Actions\SaveAttribute;
use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\Models\ProductAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttributeVariantIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_attributes_connect_editor_storefront_filters_and_cart_to_the_correct_sku(): void
    {
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        $attribute = app(SaveAttribute::class)->handle(null, ['name' => 'Storage', 'display_type' => 'swatches', 'values' => [['value' => '128 GB', 'color' => '#112233'], ['value' => '256 GB', 'color' => '#FFFFFF']]]);
        [$small, $large] = $attribute->values->pluck('id')->all();
        $product = app(SaveProduct::class)->handle(null, [
            'name' => 'Connected phone', 'type' => 'variable', 'status' => 'published', 'option_attribute_ids' => [$attribute->id],
            'variants' => [
                ['sku' => 'PHONE-128', 'price' => '100', 'option_value_ids' => [$small], 'initial_stock' => 2],
                ['sku' => 'PHONE-256', 'price' => '150', 'option_value_ids' => [$large], 'initial_stock' => 4],
            ],
        ], 'USD');
        $largeVariant = $product->variants->firstWhere('sku', 'PHONE-256');
        $image = $product->images()->create(['disk' => 'public', 'path' => 'products/large.png', 'position' => 1]);
        $product->images()->create(['disk' => 'public', 'path' => 'products/default.png', 'position' => 0]);
        $largeVariant->update(['image_id' => $image->id]);
        $this->actingAs($this->userWithRole('catalog-manager'));
        $this->get(route('admin.attributes.index'))->assertInertia(fn (Assert $page) => $page->where('attributes.0.product_count', 1));
        $this->get('/admin/products?attribute='.$attribute->id)->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
        $this->get(route('admin.products.edit', $product))->assertInertia(fn (Assert $page) => $page->where('product.option_attribute_ids.0', $attribute->id));
        $this->get(route('storefront.product', $product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('product.options.0.display_type', 'swatches')->where('product.options.0.values.1.color', '#FFFFFF')->where('product.options.0.name', 'Storage')->has('product.options.0.values', 2)
            ->where('product.variants.1.option_value_ids.0', $large)->where('product.variants.1.price.amount', '150.00'));
        $this->post(route('cart.items.store'), ['variant_id' => $largeVariant->id, 'quantity' => 2, 'price' => '1'])->assertSessionHasNoErrors();
        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
            ->where('cart.lines.0.sku', 'PHONE-256')->where('cart.lines.0.variant_name', '256 GB')
            ->where('cart.lines.0.image.url', $image->url())->where('cart.subtotal.amount', '300.00'));
        $this->post(route('cart.items.store'), ['variant_id' => $largeVariant->id, 'quantity' => 3])->assertSessionHasErrors('quantity');
    }

    public function test_selector_styles_and_color_validation_preserve_existing_value_links(): void
    {
        $this->installPlatform();
        $this->withoutVite();
        $this->actingAs($this->userWithRole('catalog-manager'));
        foreach (['buttons', 'circles', 'swatches', 'dropdown', 'radio'] as $style) {
            $this->post(route('admin.attributes.store'), ['name' => $style, 'display_type' => $style, 'values' => [['value' => 'Black', 'color' => '#000000']]])->assertSessionHasNoErrors();
            $attribute = ProductAttribute::where('name', $style)->firstOrFail();
            $this->assertSame($style, $attribute->display_type);
            $value = $attribute->values->firstOrFail();
            $this->get(route('admin.attributes.edit', $attribute))->assertInertia(fn (Assert $page) => $page->where('attribute.display_type', $style)->where('attribute.values.0.color', '#000000'));
            $this->put(route('admin.attributes.update', $attribute), ['name' => $style, 'display_type' => 'circles', 'values' => [['id' => $value->id, 'value' => 'Black', 'color' => '#123456']]])->assertSessionHasNoErrors();
            $this->assertSame($value->id, $attribute->fresh()->values->firstOrFail()->id);
        }
        $this->post(route('admin.attributes.store'), ['name' => 'Bad style', 'display_type' => 'invalid', 'values' => [['value' => 'X']]])->assertSessionHasErrors('display_type');
        foreach ([null, 'red', '#fff', 'url(test)'] as $color) {
            $this->post(route('admin.attributes.store'), ['name' => 'Bad color', 'display_type' => 'swatches', 'values' => [['value' => 'X', 'color' => $color]]])->assertSessionHasErrors('values.0.color');
        }
        $this->assertDatabaseMissing('product_attributes', ['name' => 'Bad color']);
    }
}
