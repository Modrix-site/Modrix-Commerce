<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Domains\Catalog\Models\Category;
use App\Domains\Orders\Models\Order;
use EcoModular\ProductAddons\Models\ProductAddon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class ProductAddonsTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/product-addons';

    private const SLOT = 'modrixcommerce/product-addons/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules'), $this->modulesPath);
        $modules = app(ModuleManager::class);
        $modules->install(self::MODULE);
        $modules->enable(self::MODULE, true);
        $this->enableCheckout();
        config(['inertia.ssr.enabled' => false]);
    }

    private function addon(array $attributes = []): ProductAddon
    {
        return ProductAddon::query()->create(array_merge([
            'name' => '2-Year Extended Warranty',
            'type' => ProductAddon::TYPE_WARRANTY,
            'badge' => '2 years',
            'description' => 'Full accidental damage & hardware coverage.',
            'price' => '15.0000',
            'target_type' => ProductAddon::TARGET_ALL,
            'target_ids' => [],
            'priority' => 0,
            'is_active' => true,
        ], $attributes));
    }

    /** @return list<array<string, mixed>> */
    private function productAddonBlocks(string $slug, string $place = 'product.after_add_to_cart'): array
    {
        $blocks = [];
        $this->get(route('storefront.product', $slug))->assertOk()->assertInertia(function (Assert $page) use (&$blocks, $place) {
            $blocks = $page->toArray()['props']['placeSlots'][$place] ?? [];
        });

        return array_values(array_filter($blocks, fn (array $b) => $b['component'] === self::SLOT.'ProductAddons'));
    }

    public function test_addons_target_specific_products_categories_or_all_products(): void
    {
        $category = Category::factory()->create(['name' => 'Cameras', 'slug' => 'cameras']);
        $otherCategory = Category::factory()->create(['name' => 'Shoes', 'slug' => 'shoes']);
        $camera = $this->sellable('200.00', 10, [], ['name' => 'Pro Camera', 'slug' => 'pro-camera']);
        $camera->product->categories()->attach($category);

        $shoes = $this->sellable('80.00', 10, [], ['name' => 'Running Shoes', 'slug' => 'running-shoes']);
        $shoes->product->categories()->attach($otherCategory);

        $productAddon = $this->addon([
            'name' => 'Lens Care Plan',
            'price' => '25.00',
            'target_type' => ProductAddon::TARGET_PRODUCTS,
            'target_ids' => [$camera->product_id],
            'priority' => 10,
        ]);
        $categoryAddon = $this->addon([
            'name' => '2-Year Camera Warranty',
            'price' => '30.00',
            'target_type' => ProductAddon::TARGET_CATEGORIES,
            'target_ids' => [$category->id],
            'priority' => 5,
        ]);
        $globalAddon = $this->addon([
            'name' => 'Priority Gift Packaging',
            'type' => ProductAddon::TYPE_FEATURE,
            'price' => '5.00',
            'target_type' => ProductAddon::TARGET_ALL,
            'priority' => 1,
        ]);
        $this->addon([
            'name' => 'Inactive Warranty',
            'is_active' => false,
        ]);

        $cameraBlocks = $this->productAddonBlocks($camera->product->slug);
        $this->assertCount(1, $cameraBlocks);
        $this->assertSame(
            [$productAddon->id, $categoryAddon->id, $globalAddon->id],
            array_column($cameraBlocks[0]['props']['addons'], 'id'),
        );

        $shoeBlocks = $this->productAddonBlocks($shoes->product->slug);
        $this->assertCount(1, $shoeBlocks);
        $this->assertSame([$globalAddon->id], array_column($shoeBlocks[0]['props']['addons'], 'id'));
    }

    public function test_selecting_addons_adds_their_price_to_the_cart_and_checkout_order_total(): void
    {
        $laptop = $this->sellable('100.00', 10, [], ['name' => 'Studio Laptop', 'slug' => 'studio-laptop']);
        $warranty = $this->addon([
            'name' => '2-Year Extended Warranty',
            'type' => ProductAddon::TYPE_WARRANTY,
            'price' => '20.00',
            'target_type' => ProductAddon::TARGET_PRODUCTS,
            'target_ids' => [$laptop->product_id],
        ]);
        $feature = $this->addon([
            'name' => 'Pre-installed Calibration Profile',
            'type' => ProductAddon::TYPE_FEATURE,
            'price' => '10.00',
            'target_type' => ProductAddon::TARGET_PRODUCTS,
            'target_ids' => [$laptop->product_id],
        ]);

        // Select both addons on the product page before adding to cart.
        $this->post(route('modrixcommerce.product-addons.toggle'), [
            'product_id' => $laptop->product_id,
            'addon_id' => $warranty->id,
        ])->assertRedirect();

        $this->post(route('modrixcommerce.product-addons.toggle'), [
            'product_id' => $laptop->product_id,
            'addon_id' => $feature->id,
        ])->assertRedirect();

        // Product page variant price now includes the selected addons ($100 + $20 + $10 = $130.00).
        $this->get(route('storefront.product', $laptop->product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.variants.0.base_price.amount', '100.00')
                ->where('product.variants.0.price.amount', '130.00'));

        $this->addToCart($laptop, 2);

        $cart = null;
        $this->get(route('cart.show'))->assertOk()->assertInertia(function (Assert $page) use (&$cart) {
            $cart = $page->toArray()['props']['cart'];
        });

        // Base $100 + $20 warranty + $10 feature = $130 per unit × 2 = $260 subtotal.
        $this->assertSame('100.00', $cart['lines'][0]['base_unit_price']['amount']);
        $this->assertSame('200.00', $cart['lines'][0]['base_subtotal']['amount']);
        $this->assertSame('60.00', $cart['lines'][0]['addons_subtotal']['amount']);
        $this->assertCount(2, $cart['lines'][0]['addons']);
        $this->assertSame('130.00', $cart['lines'][0]['unit_price']['amount']);
        $this->assertSame('260.00', $cart['lines'][0]['subtotal']['amount']);
        $this->assertSame('200.00', $cart['items_subtotal']['amount']);
        $this->assertSame('60.00', $cart['addons_total']['amount']);
        $this->assertSame('260.00', $cart['subtotal']['amount']);
        $this->assertStringContainsString('2-Year Extended Warranty', (string) $cart['lines'][0]['variant_name']);
        $this->assertStringContainsString('Pre-installed Calibration Profile', (string) $cart['lines'][0]['variant_name']);

        // Checkout creates the order with the addon price included in subtotal & grand_total.
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        $order = Order::query()->with('items')->latest('id')->firstOrFail();
        $this->assertSame('260.0000', $order->subtotal);
        // Free shipping over $100.00 applies, so grand_total = $260.00.
        $this->assertSame('260.0000', $order->grand_total);
        $this->assertSame('130.0000', $order->items->first()->unit_price);
        $this->assertStringContainsString('2-Year Extended Warranty', (string) $order->items->first()->variant_name);
    }

    public function test_admins_can_create_edit_and_delete_customizable_addons(): void
    {
        $owner = $this->owner();
        $product = $this->sellable('50.00');

        $payload = [
            'name' => '3-Year VIP Warranty',
            'type' => ProductAddon::TYPE_WARRANTY,
            'badge' => '3 years',
            'description' => 'Express replacement within 24 hours.',
            'price' => '29.99',
            'target_type' => ProductAddon::TARGET_PRODUCTS,
            'target_ids' => [$product->product_id],
            'priority' => 5,
            'is_active' => true,
        ];

        $this->actingAs($owner)
            ->get(route('admin.modrixcommerce.product-addons.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('module/modrixcommerce/product-addons/admin/index', false));

        $this->actingAs($owner)
            ->post(route('admin.modrixcommerce.product-addons.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $addon = ProductAddon::query()->firstOrFail();
        $this->assertSame('3-Year VIP Warranty', $addon->name);
        $this->assertSame('29.9900', $addon->price);
        $this->assertSame([$product->product_id], $addon->target_ids);

        $this->actingAs($owner)
            ->put(route('admin.modrixcommerce.product-addons.update', $addon), [
                ...$payload,
                'name' => '5-Year Ultimate Warranty',
                'price' => '49.00',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('5-Year Ultimate Warranty', $addon->fresh()->name);
        $this->assertSame('49.0000', $addon->fresh()->price);

        $this->actingAs($owner)
            ->delete(route('admin.modrixcommerce.product-addons.destroy', $addon))
            ->assertRedirect();

        $this->assertSame(0, ProductAddon::query()->count());
    }
}
