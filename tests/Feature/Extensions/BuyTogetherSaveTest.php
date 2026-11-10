<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Checkout\Pricing\CartPricer;
use EcoModular\BuyTogetherSave\BuyTogetherDealResolver;
use EcoModular\BuyTogetherSave\BuyTogetherPricingStep;
use EcoModular\BuyTogetherSave\BuyTogetherSettings;
use EcoModular\BuyTogetherSave\Models\BuyTogetherDeal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Feature tests for the 'Buy Together & Save' module:
 * - Module lifecycle and route registration
 * - Deal matching and smart category fallback
 * - Cart bundle addition and conversion tracking
 * - Cart pricer line discount application
 * - Admin CRUD and settings management
 */
class BuyTogetherSaveTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/buy-together-save';

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

    public function test_module_is_enabled_and_registers_its_routes_and_services(): void
    {
        $this->assertSame(ModuleState::Enabled, $this->state(self::MODULE));

        $this->assertTrue(app('router')->has('admin.modrixcommerce.buy-together-save.index'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.buy-together-save.create'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.buy-together-save.store'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.buy-together-save.settings'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.buy-together-save.settings.update'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.buy-together-save.edit'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.buy-together-save.update'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.buy-together-save.destroy'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.buy-together-save.toggle'));
        $this->assertTrue(app('router')->has('modrixcommerce.buy-together-save.add'));

        $this->assertInstanceOf(BuyTogetherSettings::class, app(BuyTogetherSettings::class));
        $this->assertInstanceOf(BuyTogetherDealResolver::class, app(BuyTogetherDealResolver::class));
        $this->assertInstanceOf(BuyTogetherPricingStep::class, app(BuyTogetherPricingStep::class));
    }

    public function test_deal_resolver_matches_product_deal_and_calculates_savings(): void
    {
        $a = $this->sellable('50.00');
        $b = $this->sellable('30.00');

        $deal = BuyTogetherDeal::create([
            'title' => 'Camera + Lens Kit Deal',
            'badge_text' => 'Save 20%',
            'description' => 'Get 20% off when bought together',
            'trigger_type' => 'product',
            'trigger_ids' => [$a->product_id],
            'bundle_product_ids' => [$b->product_id],
            'discount_type' => 'percent',
            'discount_value' => 20.0,
            'min_items_required' => 2,
            'priority' => 10,
            'is_active' => true,
        ]);

        $resolver = app(BuyTogetherDealResolver::class);
        $data = $resolver->resolveForProduct($a->product);

        $this->assertNotNull($data);
        $this->assertSame($deal->id, $data['deal_id']);
        $this->assertSame(80.0, (float) $data['total_original']);
        $this->assertSame(16.0, (float) $data['savings_amount']);
        $this->assertSame(64.0, (float) $data['total_bundle']);
        $this->assertCount(1, $data['items']);
        $this->assertSame($b->product_id, $data['items'][0]['id']);

        // Check storefront slot injection on product page
        $this->get(route('storefront.product', $a->product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('slots.0.component', 'modrixcommerce/buy-together-save/BuyTogetherSaveWidget')
                ->where('slots.0.props.deal_id', $deal->id)
                ->where('slots.0.props.main_item.id', $a->product_id)
                ->where('slots.0.props.items.0.id', $b->product_id));
    }

    public function test_deal_resolver_falls_back_to_same_category_when_allowed(): void
    {
        $category = Category::factory()->create();
        $a = $this->sellable('40.00');
        $b = $this->sellable('25.00');
        $a->product->categories()->attach($category);
        $b->product->categories()->attach($category);

        $resolver = app(BuyTogetherDealResolver::class);
        $data = $resolver->resolveForProduct($a->product);

        $this->assertNotNull($data);
        $this->assertNull($data['deal_id']); // Smart category fallback
        $this->assertSame(65.0, (float) $data['total_original']);
        // Default 10% discount: 10% of 65.00 = 6.50
        $this->assertSame(6.5, (float) $data['savings_amount']);
        $this->assertSame(58.5, (float) $data['total_bundle']);
        $this->assertSame($b->product_id, $data['items'][0]['id']);
    }

    public function test_deal_resolver_returns_null_when_module_is_disabled(): void
    {
        $a = $this->sellable('50.00');
        $b = $this->sellable('30.00');

        BuyTogetherDeal::create([
            'title' => 'Disabled Deal',
            'trigger_type' => 'product',
            'trigger_ids' => [$a->product_id],
            'bundle_product_ids' => [$b->product_id],
            'discount_type' => 'percent',
            'discount_value' => 15.0,
            'is_active' => true,
        ]);

        $settings = app(BuyTogetherSettings::class);
        $settings->save(['enabled' => false]);

        $resolver = app(BuyTogetherDealResolver::class);
        $this->assertNull($resolver->resolveForProduct($a->product));

        $this->get(route('storefront.product', $a->product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('slots', 0));
    }

    public function test_storefront_add_bundle_adds_all_items_to_cart_and_records_tracking(): void
    {
        $a = $this->sellable('50.00');
        $b = $this->sellable('30.00');

        $deal = BuyTogetherDeal::create([
            'title' => 'Electronics Duo',
            'trigger_type' => 'product',
            'trigger_ids' => [$a->product_id],
            'bundle_product_ids' => [$b->product_id],
            'discount_type' => 'percent',
            'discount_value' => 20.0,
            'is_active' => true,
        ]);

        $response = $this->post(route('modrixcommerce.buy-together-save.add'), [
            'product_id' => $a->product_id,
            'variant_ids' => [$a->id, $b->id],
            'deal_id' => $deal->id,
        ]);

        $response->assertStatus(303);
        $response->assertSessionHasNoErrors();

        $cart = Cart::query()->latest('id')->firstOrFail();
        $this->assertCount(2, $cart->items);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $cart->items->pluck('variant_id')->all());

        // Verify tracking rows in buy_together_cart_lines
        $lines = DB::table('buy_together_cart_lines')->where('cart_id', $cart->id)->get();
        $this->assertCount(2, $lines);
        $this->assertSame($lines->first()->bundle_group_id, $lines->last()->bundle_group_id);
        $this->assertSame($deal->id, (int) $lines->first()->deal_id);

        // Conversions count was incremented
        $this->assertSame(1, $deal->fresh()->conversions_count);
    }

    public function test_storefront_add_bundle_rejects_unavailable_or_insufficient_variants(): void
    {
        $a = $this->sellable('50.00');

        // Less than 2 items
        $this->post(route('modrixcommerce.buy-together-save.add'), [
            'product_id' => $a->product_id,
            'variant_ids' => [$a->id],
        ])->assertSessionHasErrors(['variant_ids']);

        // Non-existent variant ID
        $this->post(route('modrixcommerce.buy-together-save.add'), [
            'product_id' => $a->product_id,
            'variant_ids' => [$a->id, 999999],
        ])->assertStatus(422);
    }

    public function test_pricing_step_applies_bundle_savings_to_cart_lines(): void
    {
        $a = $this->sellable('100.00');
        $b = $this->sellable('50.00');

        $deal = BuyTogetherDeal::create([
            'title' => 'Save 20% on Duo',
            'badge_text' => 'Save 20%',
            'trigger_type' => 'product',
            'trigger_ids' => [$a->product_id],
            'bundle_product_ids' => [$b->product_id],
            'discount_type' => 'percent',
            'discount_value' => 20.0,
            'is_active' => true,
        ]);

        // Add bundle
        $this->post(route('modrixcommerce.buy-together-save.add'), [
            'product_id' => $a->product_id,
            'variant_ids' => [$a->id, $b->id],
            'deal_id' => $deal->id,
        ]);

        $cart = Cart::query()->latest('id')->firstOrFail();
        $pricer = app(CartPricer::class);
        $priced = $pricer->price($cart);

        // Line a: $100 -> 20% off = $20 discount
        // Line b: $50  -> 20% off = $10 discount
        // Total discount: $30
        $this->assertSame('150.0000', (string) $priced->subtotal()->amount());
        $this->assertSame('30.0000', (string) $priced->discountTotal->amount());
        $this->assertSame('120.0000', (string) $priced->grandTotal()->amount());

        // Check individual lines
        $lineA = collect($priced->lines)->firstWhere('variant.id', $a->id);
        $lineB = collect($priced->lines)->firstWhere('variant.id', $b->id);

        $this->assertNotNull($lineA);
        $this->assertNotNull($lineB);
        $this->assertSame('20.0000', (string) $lineA->discount->amount());
        $this->assertSame('10.0000', (string) $lineB->discount->amount());

        // If one item is removed from cart, bundle criteria (< 2 items) is violated and discount is removed
        $cart->items()->where('variant_id', $b->id)->delete();
        $pricedAfterRemoval = $pricer->price($cart->fresh());
        $this->assertSame('100.0000', (string) $pricedAfterRemoval->subtotal()->amount());
        $this->assertSame('0.0000', (string) $pricedAfterRemoval->discountTotal->amount());
    }

    public function test_pricing_step_applies_fixed_bundle_savings(): void
    {
        $a = $this->sellable('60.00');
        $b = $this->sellable('40.00');

        $deal = BuyTogetherDeal::create([
            'title' => 'Flat $20 Off Bundle',
            'badge_text' => '$20 Off',
            'trigger_type' => 'product',
            'trigger_ids' => [$a->product_id],
            'bundle_product_ids' => [$b->product_id],
            'discount_type' => 'fixed',
            'discount_value' => 20.0,
            'is_active' => true,
        ]);

        $this->post(route('modrixcommerce.buy-together-save.add'), [
            'product_id' => $a->product_id,
            'variant_ids' => [$a->id, $b->id],
            'deal_id' => $deal->id,
        ]);

        $cart = Cart::query()->latest('id')->firstOrFail();
        $priced = app(CartPricer::class)->price($cart);

        // Subtotal $100 - $20 discount = $80 total
        $this->assertSame('100.0000', (string) $priced->subtotal()->amount());
        $this->assertSame('20.0000', (string) $priced->discountTotal->amount());
        $this->assertSame('80.0000', (string) $priced->grandTotal()->amount());
    }

    public function test_admin_crud_lifecycle_and_settings(): void
    {
        $a = $this->sellable('50.00');
        $b = $this->sellable('30.00');

        // Unauthenticated/unauthorized access denied
        $this->actingAs($this->userWithRole('catalog-manager'))
            ->get(route('admin.modrixcommerce.buy-together-save.index'))
            ->assertForbidden();

        $admin = $this->owner();

        // 1. Index page
        $this->actingAs($admin)
            ->get(route('admin.modrixcommerce.buy-together-save.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/buy-together-save/admin/index', false)
                ->has('deals')
                ->has('stats')
                ->has('settings'));

        // 2. Create page
        $this->actingAs($admin)
            ->get(route('admin.modrixcommerce.buy-together-save.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/buy-together-save/admin/edit', false)
                ->has('products')
                ->has('categories'));

        // 3. Store new deal
        $storeResponse = $this->actingAs($admin)
            ->post(route('admin.modrixcommerce.buy-together-save.store'), [
                'title' => 'Holiday Special Bundle',
                'badge_text' => 'Save 15%',
                'description' => 'Special holiday bundle',
                'trigger_type' => 'product',
                'trigger_ids' => [$a->product_id],
                'bundle_product_ids' => [$b->product_id],
                'discount_type' => 'percent',
                'discount_value' => 15.0,
                'min_items_required' => 2,
                'priority' => 5,
                'is_active' => true,
            ]);

        $storeResponse->assertRedirect(route('admin.modrixcommerce.buy-together-save.index'));
        $deal = BuyTogetherDeal::query()->where('title', 'Holiday Special Bundle')->first();
        $this->assertNotNull($deal);
        $this->assertSame(15.0, (float) $deal->discount_value);

        // 4. Edit page
        $this->actingAs($admin)
            ->get(route('admin.modrixcommerce.buy-together-save.edit', $deal))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/buy-together-save/admin/edit', false)
                ->where('deal.id', $deal->id));

        // 5. Update deal
        $this->actingAs($admin)
            ->put(route('admin.modrixcommerce.buy-together-save.update', $deal), [
                'title' => 'Updated Holiday Bundle',
                'badge_text' => 'Save 25%',
                'description' => 'Updated description',
                'trigger_type' => 'product',
                'trigger_ids' => [$a->product_id],
                'bundle_product_ids' => [$b->product_id],
                'discount_type' => 'percent',
                'discount_value' => 25.0,
                'min_items_required' => 2,
                'priority' => 8,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.modrixcommerce.buy-together-save.index'));

        $this->assertSame('Updated Holiday Bundle', $deal->fresh()->title);
        $this->assertSame(25.0, (float) $deal->fresh()->discount_value);

        // 6. Toggle active status
        $this->actingAs($admin)
            ->post(route('admin.modrixcommerce.buy-together-save.toggle', $deal))
            ->assertStatus(303);
        $this->assertFalse($deal->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('admin.modrixcommerce.buy-together-save.toggle', $deal))
            ->assertStatus(303);
        $this->assertTrue($deal->fresh()->is_active);

        // 7. Settings page & update
        $this->actingAs($admin)
            ->get(route('admin.modrixcommerce.buy-together-save.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/buy-together-save/admin/settings', false)
                ->has('settings'));

        $this->actingAs($admin)
            ->put(route('admin.modrixcommerce.buy-together-save.settings.update'), [
                'enabled' => true,
                'heading' => 'Frequently Paired Together',
                'subheading' => 'Save big when you combine these items',
                'default_discount_type' => 'percent',
                'default_discount_value' => 12.5,
                'auto_bundle_fallback' => true,
                'cta_button_text' => 'Claim Paired Offer',
                'show_savings_badge' => true,
            ])
            ->assertRedirect(route('admin.modrixcommerce.buy-together-save.settings'));

        $settings = app(BuyTogetherSettings::class);
        $this->assertSame('Frequently Paired Together', $settings->heading());
        $this->assertSame('Claim Paired Offer', $settings->ctaButtonText());
        $this->assertSame(12.5, $settings->defaultDiscountValue());

        // 8. Delete deal
        $this->actingAs($admin)
            ->delete(route('admin.modrixcommerce.buy-together-save.destroy', $deal))
            ->assertRedirect(route('admin.modrixcommerce.buy-together-save.index'));

        $this->assertNull(BuyTogetherDeal::query()->find($deal->id));
    }

    /**
     * A 20% deal on a 100.00 + 50.00 pair.
     *
     * @return array{0: ProductVariant, 1: ProductVariant, 2: BuyTogetherDeal}
     */
    private function duoDeal(array $overrides = []): array
    {
        $a = $this->sellable('100.00', 50);
        $b = $this->sellable('50.00', 50);
        $deal = BuyTogetherDeal::create($overrides + [
            'title' => 'Duo',
            'badge_text' => 'Best value',
            'trigger_type' => 'product',
            'trigger_ids' => [$a->product_id],
            'bundle_product_ids' => [$b->product_id],
            'discount_type' => 'percent',
            'discount_value' => 20.0,
            'is_active' => true,
        ]);

        return [$a, $b, $deal];
    }

    private function addBundle(ProductVariant $main, array $variants, ?int $dealId = null)
    {
        return $this->post(route('modrixcommerce.buy-together-save.add'), [
            'product_id' => $main->product_id,
            'variant_ids' => array_map(fn (ProductVariant $v) => $v->id, $variants),
            'deal_id' => $dealId,
        ]);
    }

    private function cartDiscount(): string
    {
        $cart = Cart::query()->latest('id')->first();

        return $cart ? (string) app(CartPricer::class)->price($cart)->discountTotal->amount() : '0.0000';
    }

    public function test_each_bundle_added_discounts_one_set_only(): void
    {
        [$a, $b, $deal] = $this->duoDeal();

        $this->addBundle($a, [$a, $b], $deal->id);
        $this->addBundle($a, [$a, $b], $deal->id);

        // Two sets of 150.00 at 20%: 60.00, not 20% of each line twice.
        $this->assertSame('60.0000', $this->cartDiscount());
    }

    public function test_extra_quantity_is_not_discounted(): void
    {
        [$a, $b, $deal] = $this->duoDeal();
        $this->addBundle($a, [$a, $b], $deal->id);

        Cart::query()->latest('id')->firstOrFail()->items()->where('variant_id', $b->id)->update(['quantity' => 10]);

        $this->assertSame('30.0000', $this->cartDiscount());
    }

    public function test_fixed_discount_applies_once_per_set(): void
    {
        [$a, $b, $deal] = $this->duoDeal(['discount_type' => 'fixed', 'discount_value' => 10.0]);
        $this->addBundle($a, [$a, $b], $deal->id);

        Cart::query()->latest('id')->firstOrFail()->items()->where('variant_id', $a->id)->update(['quantity' => 3]);

        $priced = app(CartPricer::class)->price(Cart::query()->latest('id')->firstOrFail());
        $this->assertSame('10.0000', (string) $priced->discountTotal->amount());
        $lineA = collect($priced->lines)->firstWhere('variant.id', $a->id);
        // Split by one unit of each: 100 / 150 of 10.00.
        $this->assertSame('6.6700', (string) $lineA->discount->amount());
    }

    public function test_products_outside_the_offer_are_rejected(): void
    {
        [$a, $b, $deal] = $this->duoDeal();
        $x = $this->sellable('900.00', 5);
        $y = $this->sellable('800.00', 5);

        // The deal's id with products it does not offer.
        $this->addBundle($a, [$a, $x], $deal->id)->assertStatus(422);
        $this->addBundle($x, [$x, $y], $deal->id)->assertStatus(422);
        // A main product without any bundle offer.
        $this->addBundle($x, [$x, $y])->assertStatus(422);

        $this->assertSame('0.0000', $this->cartDiscount());
        $this->assertSame(0, DB::table('buy_together_cart_lines')->count());
    }

    public function test_the_offer_decides_the_deal_not_the_request(): void
    {
        [$a, $b, $deal] = $this->duoDeal();

        // No deal id sent: the deal offered on the product page still applies.
        $this->addBundle($a, [$a, $b])->assertStatus(303);

        $this->assertSame($deal->id, (int) DB::table('buy_together_cart_lines')->value('deal_id'));
        $this->assertSame('30.0000', $this->cartDiscount());
    }

    public function test_switched_off_deal_stops_discounting(): void
    {
        [$a, $b, $deal] = $this->duoDeal();
        $this->addBundle($a, [$a, $b], $deal->id);
        $this->assertSame('30.0000', $this->cartDiscount());

        $deal->update(['is_active' => false]);

        $this->assertSame('0.0000', $this->cartDiscount());
    }

    public function test_minimum_items_required_is_enforced(): void
    {
        [$a, $b, $deal] = $this->duoDeal(['min_items_required' => 3]);
        $c = $this->sellable('20.00', 50);
        $deal->update(['bundle_product_ids' => [$b->product_id, $c->product_id]]);

        $this->addBundle($a, [$a, $b], $deal->id)->assertStatus(422);
        $this->assertSame('0.0000', $this->cartDiscount());

        $this->addBundle($a, [$a, $b, $c], $deal->id)->assertStatus(303);
        // 20% of 170.00.
        $this->assertSame('34.0000', $this->cartDiscount());

        // Dropping below the minimum removes the bundle discount.
        Cart::query()->latest('id')->firstOrFail()->items()->where('variant_id', $c->id)->delete();
        $this->assertSame('0.0000', $this->cartDiscount());
    }

    public function test_cart_label_states_the_real_discount(): void
    {
        [$a, $b] = $this->duoDeal(['badge_text' => 'Save 15%', 'discount_type' => 'fixed', 'discount_value' => 10.0]);
        $this->addBundle($a, [$a, $b]);

        $priced = app(CartPricer::class)->price(Cart::query()->latest('id')->firstOrFail());
        $labels = collect($priced->lines)->pluck('offerLabel')->unique()->values()->all();

        $this->assertSame(['Bundle savings'], $labels);
    }

    public function test_category_fallback_bundle_still_works(): void
    {
        $category = Category::factory()->create();
        $a = $this->sellable('40.00', 10);
        $b = $this->sellable('25.00', 10);
        $a->product->categories()->attach($category);
        $b->product->categories()->attach($category);

        $this->addBundle($a, [$a, $b])->assertStatus(303);

        // Store default: 10% of 65.00.
        $this->assertSame('6.5000', $this->cartDiscount());
    }
}
