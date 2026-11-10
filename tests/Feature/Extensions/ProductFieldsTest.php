<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Domains\Catalog\Models\Product;
use EcoModular\ProductFields\Models\ProductField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * The bundled "Product fields" module: field definitions (Products →
 * Product fields), per-product values from the editor panel, and the
 * details block on the product page.
 */
class ProductFieldsTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/product-fields';

    private const ROUTE = 'admin.modrixcommerce.product-fields.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules'), $this->modulesPath);
        $modules = app(ModuleManager::class);
        $modules->install(self::MODULE);
        $modules->enable(self::MODULE, true);
        config(['inertia.ssr.enabled' => false]);
    }

    private function field(array $attributes = []): ProductField
    {
        static $n = 0;
        $n++;

        return ProductField::query()->create(array_merge([
            'key' => "field_{$n}",
            'label' => "Field {$n}",
            'type' => 'text',
            'options' => [],
            'required' => false,
            'show_on_storefront' => true,
            'position' => $n,
        ], $attributes));
    }

    private function product(): Product
    {
        return $this->sellable('20.00')->product;
    }

    /** @return list<array<string, mixed>> the product page's details block */
    private function details(Product $product): array
    {
        $blocks = [];
        $this->get(route('storefront.product', $product->slug))->assertOk()->assertInertia(function (Assert $page) use (&$blocks) {
            foreach ($page->toArray()['props']['placeSlots'] ?? [] as $items) {
                foreach ($items as $item) {
                    if ($item['component'] === self::MODULE.'/ProductDetails') {
                        $blocks[] = $item;
                    }
                }
            }
        });

        return $blocks;
    }

    public function test_staff_create_fields_with_a_key_and_type_specific_rules()
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route(self::ROUTE.'store'), ['label' => '', 'type' => 'colour'])
            ->assertSessionHasErrors(['label', 'type']);
        $this->actingAs($owner)->post(route(self::ROUTE.'store'), ['label' => 'Size guide', 'type' => 'select', 'options' => []])
            ->assertSessionHasErrors('options');

        $this->actingAs($owner)->post(route(self::ROUTE.'store'), [
            'label' => 'Material', 'type' => 'select', 'options' => ['Cotton', 'Linen', 'Cotton'],
            'help' => 'Main fabric', 'required' => true, 'show_on_storefront' => true,
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route(self::ROUTE.'store'), ['label' => 'Material', 'type' => 'text'])->assertSessionHasNoErrors();

        $fields = ProductField::query()->orderBy('id')->get();
        $this->assertSame(['material', 'material_2'], $fields->pluck('key')->all());
        $this->assertSame(['Cotton', 'Linen'], $fields[0]->options);
        $this->assertTrue($fields[0]->required);

        $this->actingAs($owner)->get(route(self::ROUTE.'index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('fields', 2)->where('fields.0.label', 'Material'));
    }

    public function test_values_are_validated_by_type_and_saved_per_product()
    {
        $product = $this->product();
        $number = $this->field(['label' => 'Weight (kg)', 'type' => 'number']);
        $choice = $this->field(['label' => 'Material', 'type' => 'select', 'options' => ['Cotton', 'Linen']]);
        $flag = $this->field(['label' => 'Handmade', 'type' => 'boolean']);
        $date = $this->field(['label' => 'Released', 'type' => 'date']);
        $link = $this->field(['label' => 'Manual', 'type' => 'url']);
        $required = $this->field(['label' => 'Care', 'type' => 'textarea', 'required' => true]);
        $url = route(self::ROUTE.'product.update', $product);

        $this->actingAs($this->owner())->put($url, ['values' => [
            $number->id => 'heavy', $choice->id => 'Silk', $date->id => '23/10/2026', $link->id => 'javascript:alert(1)', $required->id => '',
        ]])->assertSessionHasErrors(["values.{$number->id}", "values.{$choice->id}", "values.{$date->id}", "values.{$link->id}", "values.{$required->id}"]);

        $this->actingAs($this->owner())->put($url, ['values' => [
            $number->id => '1.5', $choice->id => 'Linen', $flag->id => '1', $date->id => '2026-10-23',
            $link->id => 'https://example.com/manual.pdf', $required->id => "Hand wash.\nDry flat.",
        ]])->assertSessionHasNoErrors();

        $values = DB::table('product_field_values')->where('product_id', $product->id)->pluck('value', 'product_field_id');
        $this->assertSame('1.5', $values[$number->id]);
        $this->assertSame('1', $values[$flag->id]);

        // Clearing a value removes it.
        $this->actingAs($this->owner())->put($url, ['values' => [$number->id => '', $required->id => 'Hand wash.']])->assertSessionHasNoErrors();
        $this->assertFalse(DB::table('product_field_values')->where('product_id', $product->id)->where('product_field_id', $number->id)->exists());
    }

    public function test_the_editor_panel_lists_the_fields_with_this_products_values()
    {
        $product = $this->product();
        $field = $this->field(['label' => 'Material']);
        DB::table('product_field_values')->insert(['product_id' => $product->id, 'product_field_id' => $field->id, 'value' => 'Oak', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->owner())->get(route('admin.products.edit', $product))->assertOk()
            ->assertInertia(function (Assert $page) {
                $panel = collect($page->toArray()['props']['extensionPanels'])->firstWhere('component', self::MODULE.'/ProductFieldsPanel');
                $this->assertNotNull($panel);
                $this->assertSame('Material', $panel['props']['fields'][0]['label']);
                $this->assertSame('Oak', $panel['props']['fields'][0]['value']);
            });
    }

    public function test_the_product_page_shows_only_storefront_fields_with_values()
    {
        $product = $this->product();
        $shown = $this->field(['label' => 'Material', 'position' => 2]);
        $flag = $this->field(['label' => 'Handmade', 'type' => 'boolean', 'position' => 1]);
        $hidden = $this->field(['label' => 'Supplier code', 'show_on_storefront' => false]);
        $this->field(['label' => 'Empty']);
        $this->actingAs($this->owner())->put(route(self::ROUTE.'product.update', $product), ['values' => [
            $shown->id => 'Oak', $flag->id => '1', $hidden->id => 'SUP-1',
        ]])->assertSessionHasNoErrors();
        auth()->logout();

        $blocks = $this->details($product);

        $this->assertCount(1, $blocks);
        $this->assertSame([['label' => 'Handmade', 'value' => 'Yes', 'type' => 'boolean'], ['label' => 'Material', 'value' => 'Oak', 'type' => 'text']], $blocks[0]['props']['fields']);
        // A product without values shows nothing.
        $this->assertSame([], $this->details($this->product()));
    }

    public function test_deleting_a_field_removes_its_values()
    {
        $product = $this->product();
        $field = $this->field();
        DB::table('product_field_values')->insert(['product_id' => $product->id, 'product_field_id' => $field->id, 'value' => 'x', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->owner())->delete(route(self::ROUTE.'destroy', $field))->assertRedirect();

        $this->assertSame(0, ProductField::query()->count());
        $this->assertSame(0, DB::table('product_field_values')->count());
    }

    public function test_managing_fields_and_values_needs_permission()
    {
        $product = $this->product();
        $field = $this->field();
        $support = $this->userWithRole('support-agent');

        $this->actingAs($support)->get(route(self::ROUTE.'index'))->assertForbidden();
        $this->actingAs($support)->put(route(self::ROUTE.'product.update', $product), ['values' => [$field->id => 'x']])->assertForbidden();
    }
}
