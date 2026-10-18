<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Discounts\Models\CouponRedemption;
use App\Domains\Orders\Models\Order;
use EcoModular\CrossSellUpsell\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * The bundled "Cross-sell & Upsell" module, exercised from a copy of
 * modules/: offer matching, the add/remove endpoints, offer pricing with
 * coupons, order attribution, the add-to-cart popup and the admin pages.
 */
class CrossSellUpsellTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'ecomodular/cross-sell-upsell';

    private const SLOT = 'ecomodular/cross-sell-upsell/';

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

    private function offer(array $attributes = []): Offer
    {
        return Offer::query()->create(array_merge([
            'name' => 'Test offer',
            'type' => Offer::CROSS_SELL,
            'trigger_type' => Offer::TRIGGER_ANY,
            'trigger_ids' => [],
            'suggestion_mode' => Offer::MANUAL,
            'product_ids' => [],
            'show_on_product' => true,
            'show_in_popup' => true,
            'show_in_cart' => true,
            'show_at_checkout' => true,
            'priority' => 0,
            'is_active' => true,
        ], $attributes));
    }

    private function add(Offer $offer, ProductVariant $variant, string $place = 'cart', int $quantity = 1)
    {
        return $this->post(route('ecomodular.cross-sell-upsell.add'), ['offer_id' => $offer->id, 'variant_id' => $variant->id, 'place' => $place, 'quantity' => $quantity]);
    }

    /** @return array<string, mixed> the cart page's cart */
    private function cart(): array
    {
        $cart = null;
        $this->get(route('cart.show'))->assertInertia(function (Assert $page) use (&$cart) {
            $cart = $page->toArray()['props']['cart'];
        });

        return $cart;
    }

    private function line(array $cart, ProductVariant $variant): ?array
    {
        return collect($cart['lines'])->firstWhere('variant_id', $variant->id);
    }

    /** @return list<array<string, mixed>> blocks at a product-page place */
    private function productBlocks(ProductVariant $variant, string $place = 'product.after_add_to_cart'): array
    {
        $blocks = [];
        $this->get(route('storefront.product', $variant->product->slug))->assertOk()->assertInertia(function (Assert $page) use (&$blocks, $place) {
            $blocks = $page->toArray()['props']['placeSlots'][$place] ?? [];
        });

        return array_values(array_filter($blocks, fn (array $b) => $b['component'] === self::SLOT.'OfferProducts'));
    }

    public function test_the_product_page_shows_the_matching_offer_with_its_offer_price()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $this->offer(['trigger_type' => Offer::TRIGGER_PRODUCTS, 'trigger_ids' => [$a->product_id], 'product_ids' => [$b->product_id], 'discount_type' => 'percent', 'discount_value' => '10', 'heading' => 'Goes well with']);

        $blocks = $this->productBlocks($a);

        $this->assertCount(1, $blocks);
        $this->assertSame('Goes well with', $blocks[0]['props']['heading']);
        $this->assertSame($b->product_id, $blocks[0]['props']['items'][0]['id']);
        $this->assertSame('9.00', $blocks[0]['props']['items'][0]['offer_price']['amount']);
        $this->assertSame('10.00', $blocks[0]['props']['items'][0]['price']['amount']);
        // Another product does not trigger the offer.
        $this->assertSame([], $this->productBlocks($b));
    }

    public function test_suggestions_skip_the_product_itself_cart_items_and_unavailable_products()
    {
        $a = $this->sellable('20.00');
        $inCart = $this->sellable('10.00');
        $soldOut = $this->sellable('10.00', 0);
        $hidden = $this->sellable('10.00', 10, [], ['status' => 'draft']);
        $ok = $this->sellable('10.00');
        $this->offer(['product_ids' => [$a->product_id, $inCart->product_id, $soldOut->product_id, $hidden->product_id, $ok->product_id]]);
        $this->addToCart($inCart);

        $items = $this->productBlocks($a)[0]['props']['items'];

        $this->assertSame([$ok->product_id], array_column($items, 'id'));
    }

    public function test_automatic_upsells_are_pricier_products_from_the_same_category()
    {
        $category = Category::factory()->create();
        $a = $this->sellable('20.00');
        $cheaper = $this->sellable('15.00');
        $pricier = $this->sellable('35.00');
        $elsewhere = $this->sellable('50.00');
        foreach ([$a, $cheaper, $pricier] as $v) {
            $v->product->categories()->attach($category);
        }
        $this->offer(['type' => Offer::UPSELL, 'suggestion_mode' => Offer::AUTOMATIC, 'trigger_type' => Offer::TRIGGER_CATEGORIES, 'trigger_ids' => [$category->id]]);

        $items = $this->productBlocks($a)[0]['props']['items'];

        $this->assertSame([$pricier->product_id], array_column($items, 'id'));
        $this->assertSame([], $this->productBlocks($elsewhere));
    }

    public function test_the_highest_priority_live_offer_wins_and_scheduled_offers_wait()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $c = $this->sellable('12.00');
        $this->offer(['product_ids' => [$b->product_id], 'priority' => 1]);
        $this->offer(['product_ids' => [$c->product_id], 'priority' => 5, 'heading' => 'Top']);
        $this->offer(['product_ids' => [$b->product_id], 'priority' => 9, 'starts_at' => now()->addDay()]);
        $this->offer(['product_ids' => [$b->product_id], 'priority' => 9, 'is_active' => false]);

        $blocks = $this->productBlocks($a);

        $this->assertSame('Top', $blocks[0]['props']['heading']);
    }

    public function test_adding_from_an_offer_discounts_the_line_while_a_trigger_is_in_the_cart()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $offer = $this->offer(['trigger_type' => Offer::TRIGGER_PRODUCTS, 'trigger_ids' => [$a->product_id], 'product_ids' => [$b->product_id], 'discount_type' => 'percent', 'discount_value' => '10', 'heading' => 'Bundle deal']);
        $this->addToCart($a);

        $this->add($offer, $b)->assertSessionHasNoErrors()->assertRedirect();

        $cart = $this->cart();
        $this->assertSame('1.00', $this->line($cart, $b)['offer_discount']['amount']);
        $this->assertSame('Bundle deal', $this->line($cart, $b)['offer_label']);
        $this->assertSame('1.00', $cart['offer_discount_total']['amount']);
        $this->assertSame('1.00', $cart['discount_total']['amount']);
        $this->assertSame(1, $offer->fresh()->adds);

        // Without the trigger product the regular price applies again.
        $itemId = $this->line($cart, $a)['id'];
        $this->delete(route('cart.items.destroy', $itemId));
        $cart = $this->cart();
        $this->assertSame('0.00', $cart['discount_total']['amount']);
        $this->assertSame('0.00', $this->line($cart, $b)['offer_discount']['amount']);
    }

    public function test_discounted_units_are_limited_to_the_trigger_units_and_fixed_discounts_to_the_price()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $offer = $this->offer(['product_ids' => [$b->product_id], 'discount_type' => 'fixed', 'discount_value' => '25']);
        $this->addToCart($a);

        $this->add($offer, $b, 'cart', 3)->assertSessionHasNoErrors();

        // One trigger unit → one discounted unit, never below zero.
        $this->assertSame('10.00', $this->cart()['offer_discount_total']['amount']);
    }

    public function test_the_add_endpoint_only_accepts_what_the_offer_suggests_where_it_shows()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $other = $this->sellable('10.00');
        $offer = $this->offer(['product_ids' => [$b->product_id], 'show_at_checkout' => false]);
        $inactive = $this->offer(['product_ids' => [$b->product_id], 'is_active' => false]);
        $this->addToCart($a);

        $this->add($offer, $other)->assertNotFound();
        $this->add($inactive, $b)->assertNotFound();
        $this->add($offer, $b, 'checkout')->assertNotFound();
        $this->add($offer, $b, 'nowhere')->assertSessionHasErrors('place');
        $this->assertNull($this->line($this->cart(), $b));
    }

    public function test_coupons_apply_after_offer_discounts_and_redeem_only_their_part()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $offer = $this->offer(['product_ids' => [$b->product_id], 'discount_type' => 'percent', 'discount_value' => '10']);
        $coupon = $this->coupon(['value' => '10']);
        $this->addToCart($a);
        $this->add($offer, $b);
        $this->post(route('cart.coupon.apply'), ['code' => $coupon->code])->assertSessionHasNoErrors();

        $cart = $this->cart();
        // 10% of (30.00 − 1.00 offer savings) = 2.90.
        $this->assertSame('1.00', $cart['offer_discount_total']['amount']);
        $this->assertSame('2.90', $cart['coupon_discount_total']['amount']);
        $this->assertSame('3.90', $cart['discount_total']['amount']);

        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame($coupon->code, $order->coupon_code);
        $this->assertSame('3.9000', $order->discount_total);
        // 30.00 − 3.90 + 5.00 shipping.
        $this->assertSame('31.1000', $order->grand_total);
        $this->assertSame('2.9000', CouponRedemption::query()->where('order_id', $order->id)->value('amount'));
    }

    public function test_an_order_without_a_coupon_keeps_no_coupon_code()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $offer = $this->offer(['product_ids' => [$b->product_id], 'discount_type' => 'percent', 'discount_value' => '50']);
        $this->addToCart($a);
        $this->add($offer, $b);

        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertNull($order->coupon_code);
        $this->assertSame('5.0000', $order->discount_total);
        $this->assertSame(0, CouponRedemption::query()->count());
    }

    public function test_views_adds_orders_and_revenue_are_counted()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $offer = $this->offer(['product_ids' => [$b->product_id], 'discount_type' => 'percent', 'discount_value' => '10']);

        $this->productBlocks($a);
        $this->productBlocks($a);
        $this->assertSame(1, $offer->fresh()->views);

        $this->addToCart($a);
        $this->add($offer, $b, 'product');
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $row = DB::table('cross_sell_conversions')->where('offer_id', $offer->id)->first();
        $this->assertSame($order->id, (int) $row->order_id);
        $this->assertEquals(9.0, (float) $row->revenue);
        $this->assertSame(0, DB::table('cross_sell_cart_lines')->count());

        $this->actingAs($this->owner())->get(route('admin.ecomodular.cross-sell-upsell.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('offers.0.views', 1)
                ->where('offers.0.adds', 1)
                ->where('offers.0.orders', 1)
                ->where('offers.0.revenue', ['USD' => '9.00']));
    }

    public function test_the_popup_follows_a_regular_add_to_cart_once()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $this->offer(['product_ids' => [$b->product_id], 'show_on_product' => false]);

        $this->addToCart($a);
        $this->get('/')->assertInertia(function (Assert $page) use ($a, $b) {
            $popup = collect($page->toArray()['props']['storefront']['overlays'])->firstWhere('component', self::SLOT.'AddedPopup');
            $this->assertNotNull($popup);
            $this->assertSame($a->product->name, $popup['props']['added']['name']);
            $this->assertSame([$b->product_id], array_column($popup['props']['items'], 'id'));
        });

        $this->get('/')->assertInertia(function (Assert $page) {
            $this->assertNull(collect($page->toArray()['props']['storefront']['overlays'])->firstWhere('component', self::SLOT.'AddedPopup'));
        });
    }

    public function test_the_checkout_bump_adds_and_removes_its_product()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $offer = $this->offer(['product_ids' => [$b->product_id], 'discount_type' => 'percent', 'discount_value' => '20']);
        $this->addToCart($a);

        $this->get(route('checkout.show'))->assertInertia(function (Assert $page) use ($b) {
            $blocks = $page->toArray()['props']['placeSlots']['checkout.before_place_order'];
            $bump = collect($blocks)->firstWhere('component', self::SLOT.'CheckoutBump');
            $this->assertSame($b->id, $bump['props']['items'][0]['variant_id']);
            $this->assertFalse($bump['props']['items'][0]['added']);
        });

        $this->add($offer, $b, 'checkout')->assertSessionHasNoErrors();
        $this->get(route('checkout.show'))->assertInertia(function (Assert $page) use ($b) {
            $bump = collect($page->toArray()['props']['placeSlots']['checkout.before_place_order'])->firstWhere('component', self::SLOT.'CheckoutBump');
            $this->assertSame($b->id, $bump['props']['items'][0]['variant_id']);
            $this->assertTrue($bump['props']['items'][0]['added']);
        });

        $this->post(route('ecomodular.cross-sell-upsell.remove'), ['offer_id' => $offer->id, 'variant_id' => $b->id])->assertSessionHasNoErrors();
        $this->assertNull($this->line($this->cart(), $b));

        // Only lines added from the offer can be removed this way.
        $this->post(route('ecomodular.cross-sell-upsell.remove'), ['offer_id' => $offer->id, 'variant_id' => $a->id])->assertNotFound();
    }

    public function test_the_cart_block_suggests_from_offers_matching_cart_products()
    {
        $a = $this->sellable('20.00');
        $b = $this->sellable('10.00');
        $this->offer(['trigger_type' => Offer::TRIGGER_PRODUCTS, 'trigger_ids' => [$a->product_id], 'product_ids' => [$b->product_id]]);
        $this->addToCart($a);

        $this->get(route('cart.show'))->assertInertia(function (Assert $page) use ($b) {
            $block = collect($page->toArray()['props']['placeSlots']['cart.after_lines'])->firstWhere('component', self::SLOT.'CartOffers');
            $this->assertSame([$b->product_id], array_column($block['props']['items'], 'id'));
        });
    }

    public function test_admins_create_edit_and_delete_offers_with_validation()
    {
        $owner = $this->owner();
        $b = $this->sellable('10.00');
        $base = [
            'name' => 'Accessories', 'heading' => '', 'type' => 'cross_sell', 'trigger_type' => 'any', 'trigger_ids' => [],
            'suggestion_mode' => 'manual', 'product_ids' => [$b->product_id], 'show_on_product' => true, 'show_in_popup' => true,
            'show_in_cart' => false, 'show_at_checkout' => false, 'discount_type' => 'percent', 'discount_value' => '15',
            'priority' => 3, 'starts_at' => null, 'ends_at' => null, 'is_active' => true,
        ];

        $this->actingAs($owner)->post(route('admin.ecomodular.cross-sell-upsell.store'), ['discount_value' => '95', 'product_ids' => [], 'trigger_type' => 'products'] + $base)
            ->assertSessionHasErrors(['discount_value', 'product_ids', 'trigger_ids']);
        $this->actingAs($owner)->post(route('admin.ecomodular.cross-sell-upsell.store'), ['show_on_product' => false, 'show_in_popup' => false] + $base)
            ->assertSessionHasErrors('show_on_product');

        $this->actingAs($owner)->post(route('admin.ecomodular.cross-sell-upsell.store'), $base)->assertSessionHasNoErrors();
        $offer = Offer::query()->firstOrFail();
        $this->assertSame([$b->product_id], $offer->product_ids);
        $this->assertSame('15.0000', $offer->discount_value);

        $this->actingAs($owner)->put(route('admin.ecomodular.cross-sell-upsell.update', $offer), ['name' => 'Renamed', 'discount_type' => null, 'discount_value' => null] + $base)->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $offer->fresh()->name);
        $this->assertNull($offer->fresh()->discount_type);

        $this->actingAs($owner)->delete(route('admin.ecomodular.cross-sell-upsell.destroy', $offer))->assertRedirect();
        $this->assertSame(0, Offer::query()->count());
    }

    public function test_staff_without_the_permission_cannot_manage_offers()
    {
        $this->actingAs($this->userWithRole('support-agent'))->get(route('admin.ecomodular.cross-sell-upsell.index'))->assertForbidden();
    }
}
