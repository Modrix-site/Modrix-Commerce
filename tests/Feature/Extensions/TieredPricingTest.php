<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Domains\Cart\CartManager;
use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Category;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Domains\Orders\Models\Order;
use EcoModular\TieredPricing\Models\TieredPricingRule;
use EcoModular\TieredPricing\TieredPricingResolver;
use EcoModular\TieredPricing\TieredPricingSettings;
use EcoModular\TieredPricing\TieredPricingStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Feature tests for Tiered Pricing & Volume Discounts:
 * - Module lifecycle & route registration
 * - Tiered pricing resolver & storefront slot injection
 * - Cart pricer line adjustments across quantity breaks
 * - Fixed vs percentage unit discounts
 * - Admin CRUD and settings management
 */
class TieredPricingTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/tiered-pricing';

    private ModuleManager $modules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules'), $this->modulesPath);
        $this->modules = app(ModuleManager::class);
        $this->modules->install(self::MODULE);
        $this->modules->enable(self::MODULE, true);
        $this->enableCheckout();
        config(['inertia.ssr.enabled' => false]);
    }

    private function state(string $name): ModuleState
    {
        return Extension::query()->where('name', $name)->firstOrFail()->state;
    }

    public function test_module_is_enabled_and_registers_routes_and_services(): void
    {
        $this->assertSame(ModuleState::Enabled, $this->state(self::MODULE));

        $this->assertTrue(app('router')->has('admin.modrixcommerce.tiered-pricing.index'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.tiered-pricing.create'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.tiered-pricing.store'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.tiered-pricing.settings'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.tiered-pricing.settings.update'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.tiered-pricing.edit'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.tiered-pricing.update'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.tiered-pricing.destroy'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.tiered-pricing.toggle'));

        $this->assertInstanceOf(TieredPricingSettings::class, app(TieredPricingSettings::class));
        $this->assertInstanceOf(TieredPricingResolver::class, app(TieredPricingResolver::class));
        $this->assertInstanceOf(TieredPricingStep::class, app(TieredPricingStep::class));
    }

    public function test_resolver_builds_tiered_breakdown_for_targeted_product(): void
    {
        $a = $this->sellable('100.00');

        $rule = TieredPricingRule::create([
            'title' => 'Wholesale Tiered Discount',
            'applies_to' => 'product',
            'target_ids' => [$a->product_id],
            'discount_type' => 'percent',
            'tiers' => [
                ['min_qty' => 2, 'max_qty' => 4, 'discount' => 10.0],
                ['min_qty' => 5, 'max_qty' => null, 'discount' => 20.0],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $resolver = app(TieredPricingResolver::class);
        $data = $resolver->resolveForProduct($a->product);

        $this->assertNotNull($data);
        $this->assertSame($rule->id, $data['rule_id']);
        $this->assertSame(100.0, (float) $data['base_unit_price']);
        $this->assertCount(2, $data['tiers']);

        // Tier 1: 10% off ($90.00 unit price, $10.00 savings)
        $this->assertSame(2, $data['tiers'][0]['min_qty']);
        $this->assertSame(4, $data['tiers'][0]['max_qty']);
        $this->assertSame(90.0, (float) $data['tiers'][0]['unit_price']);
        $this->assertSame(10.0, (float) $data['tiers'][0]['savings_per_unit']);

        // Tier 2: 20% off ($80.00 unit price, $20.00 savings)
        $this->assertSame(5, $data['tiers'][1]['min_qty']);
        $this->assertNull($data['tiers'][1]['max_qty']);
        $this->assertSame(80.0, (float) $data['tiers'][1]['unit_price']);
        $this->assertSame(20.0, (float) $data['tiers'][1]['savings_per_unit']);

        // Check storefront slot
        $this->get(route('storefront.product', $a->product->slug))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($rule) {
                $slots = $page->toArray()['props']['placeSlots']['product.after_price'] ?? [];
                $this->assertNotEmpty($slots);
                $this->assertSame('modrixcommerce/tiered-pricing/TieredPricingTable', $slots[0]['component']);
                $this->assertSame($rule->id, $slots[0]['props']['rule_id']);
            });
    }

    public function test_resolver_matches_category_rules(): void
    {
        $category = Category::factory()->create();
        $a = $this->sellable('50.00');
        $a->product->categories()->attach($category);

        TieredPricingRule::create([
            'title' => 'Category Bulk Discount',
            'applies_to' => 'category',
            'target_ids' => [$category->id],
            'discount_type' => 'percent',
            'tiers' => [
                ['min_qty' => 3, 'max_qty' => null, 'discount' => 15.0],
            ],
            'priority' => 5,
            'is_active' => true,
        ]);

        $resolver = app(TieredPricingResolver::class);
        $data = $resolver->resolveForProduct($a->product);

        $this->assertNotNull($data);
        $this->assertSame(42.5, (float) $data['tiers'][0]['unit_price']); // 15% off 50 = 7.50 discount
    }

    public function test_resolver_returns_null_when_module_is_disabled(): void
    {
        $a = $this->sellable('100.00');

        TieredPricingRule::create([
            'title' => 'Rule on Disabled Module',
            'applies_to' => 'all',
            'target_ids' => [],
            'discount_type' => 'percent',
            'tiers' => [['min_qty' => 2, 'max_qty' => null, 'discount' => 10.0]],
            'is_active' => true,
        ]);

        app(TieredPricingSettings::class)->save(['enabled' => false]);

        $resolver = app(TieredPricingResolver::class);
        $this->assertNull($resolver->resolveForProduct($a->product));
    }

    public function test_pricing_step_applies_volume_discounts_in_cart_across_tiers(): void
    {
        $a = $this->sellable('100.00');

        TieredPricingRule::create([
            'title' => 'Quantity Breaks',
            'applies_to' => 'product',
            'target_ids' => [$a->product_id],
            'discount_type' => 'percent',
            'tiers' => [
                ['min_qty' => 2, 'max_qty' => 4, 'discount' => 10.0],
                ['min_qty' => 5, 'max_qty' => null, 'discount' => 20.0],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $pricer = app(CartPricer::class);

        // 1. Qty = 1: No discount
        $this->addToCart($a, 1);
        $cart = Cart::query()->latest('id')->firstOrFail();
        $priced = $pricer->price($cart);
        $this->assertSame('100.0000', (string) $priced->subtotal()->amount());
        $this->assertSame('0.0000', (string) $priced->discountTotal->amount());
        $this->assertSame('100.0000', (string) $priced->grandTotal()->amount());

        // 2. Qty = 3: Qualifies for Tier 1 (10% off each item)
        // Subtotal = 3 * 100 = 300, Discount = 3 * 10 = 30, Total = 270
        $cart->items()->update(['quantity' => 3]);
        $priced = $pricer->price($cart->fresh());
        $this->assertSame('300.0000', (string) $priced->subtotal()->amount());
        $this->assertSame('30.0000', (string) $priced->discountTotal->amount());
        $this->assertSame('270.0000', (string) $priced->grandTotal()->amount());
        $this->assertSame('30.0000', (string) $priced->lines[0]->discount->amount());

        // 3. Qty = 6: Qualifies for Tier 2 (20% off each item)
        // Subtotal = 6 * 100 = 600, Discount = 6 * 20 = 120, Total = 480
        $cart->items()->update(['quantity' => 6]);
        $priced = $pricer->price($cart->fresh());
        $this->assertSame('600.0000', (string) $priced->subtotal()->amount());
        $this->assertSame('120.0000', (string) $priced->discountTotal->amount());
        $this->assertSame('480.0000', (string) $priced->grandTotal()->amount());
        $this->assertSame('120.0000', (string) $priced->lines[0]->discount->amount());
    }

    public function test_pricing_step_applies_fixed_per_unit_discount(): void
    {
        $a = $this->sellable('80.00');

        TieredPricingRule::create([
            'title' => 'Fixed Unit Discount',
            'applies_to' => 'product',
            'target_ids' => [$a->product_id],
            'discount_type' => 'fixed_per_unit',
            'tiers' => [
                ['min_qty' => 3, 'max_qty' => null, 'discount' => 15.0], // $15 off each unit
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $this->addToCart($a, 4);
        $cart = Cart::query()->latest('id')->firstOrFail();
        $priced = app(CartPricer::class)->price($cart);

        // Subtotal = 4 * 80 = 320
        // Discount = 4 * 15 = 60
        // Grand Total = 260
        $this->assertSame('320.0000', (string) $priced->subtotal()->amount());
        $this->assertSame('60.0000', (string) $priced->discountTotal->amount());
        $this->assertSame('260.0000', (string) $priced->grandTotal()->amount());
    }

    public function test_checkout_persists_the_volume_discount_on_the_order(): void
    {
        $variant = $this->sellable('100.00', 10);

        TieredPricingRule::create([
            'title' => 'Three or more',
            'applies_to' => 'product',
            'target_ids' => [$variant->product_id],
            'discount_type' => 'percent',
            'tiers' => [['min_qty' => 3, 'max_qty' => null, 'discount' => 10]],
            'is_active' => true,
        ]);

        $this->addToCart($variant, 3);
        $this->post(route('checkout.store'), $this->checkoutPayload(['expected_total' => '270.00']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $order = Order::query()->with('items')->sole();
        $this->assertSame('30.0000', $order->discount_total);
        $this->assertSame('270.0000', $order->grand_total);
        $this->assertSame(3, $order->items->sole()->quantity);
        $this->assertSame('30.0000', $order->items->sole()->discount_total);
    }

    public function test_admin_crud_lifecycle_and_settings(): void
    {
        $a = $this->sellable('50.00');

        $this->actingAs($this->userWithRole('catalog-manager'))
            ->get(route('admin.modrixcommerce.tiered-pricing.index'))
            ->assertForbidden();

        $admin = $this->owner();

        // 1. Index
        $this->actingAs($admin)
            ->get(route('admin.modrixcommerce.tiered-pricing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/tiered-pricing/admin/index', false)
                ->has('rules')
                ->has('stats')
                ->has('settings'));

        // 2. Create
        $this->actingAs($admin)
            ->get(route('admin.modrixcommerce.tiered-pricing.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/tiered-pricing/admin/edit', false)
                ->has('products')
                ->has('categories'));

        // 3. Store
        $this->actingAs($admin)
            ->post(route('admin.modrixcommerce.tiered-pricing.store'), [
                'title' => 'Bulk Tech Tiers',
                'description' => 'Bulk buy tier rules',
                'applies_to' => 'product',
                'target_ids' => [$a->product_id],
                'discount_type' => 'percent',
                'tiers' => [
                    ['min_qty' => 3, 'max_qty' => 5, 'discount' => 10.0],
                    ['min_qty' => 6, 'max_qty' => null, 'discount' => 20.0],
                ],
                'priority' => 5,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.modrixcommerce.tiered-pricing.index'));

        $rule = TieredPricingRule::query()->where('title', 'Bulk Tech Tiers')->first();
        $this->assertNotNull($rule);
        $this->assertCount(2, $rule->tiers);

        // 4. Edit
        $this->actingAs($admin)
            ->get(route('admin.modrixcommerce.tiered-pricing.edit', $rule))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/tiered-pricing/admin/edit', false)
                ->where('rule.id', $rule->id));

        // 5. Update
        $this->actingAs($admin)
            ->put(route('admin.modrixcommerce.tiered-pricing.update', $rule), [
                'title' => 'Updated Tech Tiers',
                'description' => 'Updated description',
                'applies_to' => 'product',
                'target_ids' => [$a->product_id],
                'discount_type' => 'percent',
                'tiers' => [
                    ['min_qty' => 2, 'max_qty' => null, 'discount' => 12.0],
                ],
                'priority' => 7,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.modrixcommerce.tiered-pricing.index'));

        $this->assertSame('Updated Tech Tiers', $rule->fresh()->title);
        $this->assertCount(1, $rule->fresh()->tiers);

        // 6. Toggle
        $this->actingAs($admin)
            ->post(route('admin.modrixcommerce.tiered-pricing.toggle', $rule))
            ->assertStatus(303);
        $this->assertFalse($rule->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('admin.modrixcommerce.tiered-pricing.toggle', $rule))
            ->assertStatus(303);
        $this->assertTrue($rule->fresh()->is_active);

        // 7. Settings
        $this->actingAs($admin)
            ->get(route('admin.modrixcommerce.tiered-pricing.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/tiered-pricing/admin/settings', false)
                ->has('settings'));

        $this->actingAs($admin)
            ->put(route('admin.modrixcommerce.tiered-pricing.settings.update'), [
                'enabled' => true,
                'table_heading' => 'Wholesale Pricing Grid',
                'table_subheading' => 'Order in volume to receive instant price breaks',
                'show_savings_badge' => true,
                'highlight_active_tier' => true,
            ])
            ->assertRedirect(route('admin.modrixcommerce.tiered-pricing.settings'));

        $settings = app(TieredPricingSettings::class);
        $this->assertSame('Wholesale Pricing Grid', $settings->tableHeading());

        // 8. Delete
        $this->actingAs($admin)
            ->delete(route('admin.modrixcommerce.tiered-pricing.destroy', $rule))
            ->assertRedirect(route('admin.modrixcommerce.tiered-pricing.index'));

        $this->assertNull(TieredPricingRule::query()->find($rule->id));
    }
}
