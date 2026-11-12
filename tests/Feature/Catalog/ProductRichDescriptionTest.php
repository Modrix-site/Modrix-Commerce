<?php

namespace Tests\Feature\Catalog;

use App\Core\Localization\Locales;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductRichDescriptionTest extends TestCase
{
    use RefreshDatabase;

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
                'sku' => 'MUG-RT-1',
                'price' => '20.00',
                'track_inventory' => false,
                'allow_backorder' => false,
                'is_active' => true,
            ]],
        ], $overrides);
    }

    public function test_description_html_is_cleaned_when_saved(): void
    {
        $this->actingAs($this->owner())->post(route('admin.products.store'), $this->payload([
            'description' => '<h2>Care</h2><p onclick="steal()">Hand <strong>wash</strong><script>steal()</script></p><p><a href="javascript:steal()">x</a></p>',
        ]))->assertSessionHasNoErrors();

        $description = Product::query()->where('name', 'Trail Mug')->value('description');
        $this->assertStringContainsString('<h2>Care</h2>', $description);
        $this->assertStringContainsString('<strong>wash</strong>', $description);
        $this->assertStringNotContainsString('script', $description);
        $this->assertStringNotContainsString('onclick', $description);
        $this->assertStringNotContainsString('javascript', $description);
    }

    public function test_storefront_gets_html_and_plain_text(): void
    {
        $this->actingAs($this->owner())->post(route('admin.products.store'), $this->payload([
            'description' => '<h2>Care</h2><p>Hand <strong>wash</strong></p>',
        ]))->assertSessionHasNoErrors();
        auth()->logout();

        $this->get(route('storefront.product', 'trail-mug'))->assertInertia(fn (Assert $page) => $page
            ->where('product.description_html', '<h2>Care</h2><p>Hand <strong>wash</strong></p>')
            ->where('product.description', "Care\nHand wash"));
    }

    public function test_old_plain_text_descriptions_still_show_line_breaks(): void
    {
        $this->actingAs($this->owner())->post(route('admin.products.store'), $this->payload())->assertSessionHasNoErrors();
        Product::query()->where('name', 'Trail Mug')->update(['description' => "Size < 5 & up\nLine two"]);
        auth()->logout();

        $this->get(route('storefront.product', 'trail-mug'))->assertInertia(fn (Assert $page) => $page
            ->where('product.description_html', '<p>Size &lt; 5 &amp; up<br>Line two</p>')
            ->where('product.description', "Size < 5 & up\nLine two"));
    }

    public function test_admin_editor_receives_plain_text_as_paragraphs(): void
    {
        $this->actingAs($this->owner())->post(route('admin.products.store'), $this->payload())->assertSessionHasNoErrors();
        $product = Product::query()->where('name', 'Trail Mug')->firstOrFail();
        $product->update(['description' => "First\n\nSecond"]);

        $this->get(route('admin.products.edit', $product))->assertInertia(fn (Assert $page) => $page
            ->where('product.description', '<p>First</p><p>Second</p>'));
    }

    public function test_translated_description_is_cleaned(): void
    {
        $this->actingAs($this->owner())->post(route('admin.products.store'), $this->payload())->assertSessionHasNoErrors();
        $product = Product::query()->where('name', 'Trail Mug')->firstOrFail();
        app(Locales::class)->update('en', ['fr']);

        $this->put(route('admin.products.translations.update', [$product, 'fr']), [
            'name' => 'Tasse',
            'description' => '<p>Lavage <em>main</em><script>x()</script></p>',
        ])->assertSessionHasNoErrors();

        $this->assertSame('<p>Lavage <em>main</em></p>', $product->translations()->where('locale', 'fr')->value('description'));
    }
}
