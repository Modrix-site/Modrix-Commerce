<?php

namespace Tests\Feature\Checkout;

use App\Core\Settings\SettingsRepository;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Category;
use App\Domains\Customers\Models\CustomerAddress;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
    }

    public function test_guest_checkout_creates_a_priced_snapshotted_unpaid_order()
    {
        $this->taxRate('US', '10');
        $mug = $this->sellable('12.50', 5, product: ['name' => 'Mug']);
        $tee = $this->sellable('20.00', 3, product: ['name' => 'Tee']);
        $this->addToCart($mug, 2);
        $this->addToCart($tee, 1);

        $response = $this->post(route('checkout.store'), $this->checkoutPayload());

        $order = Order::query()->with('items', 'addresses')->firstOrFail();
        $response->assertRedirect(route('checkout.complete', $order));

        // 2 × 12.50 + 20.00 = 45.00; shipping 5.00 (under 100); tax 10% of 45.00 = 4.50.
        $this->assertSame('45.0000', $order->subtotal);
        $this->assertSame('5.0000', $order->shipping_total);
        $this->assertSame('4.5000', $order->tax_total);
        $this->assertSame('54.5000', $order->grand_total);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(FulfillmentStatus::Unfulfilled, $order->fulfillment_status);
        $this->assertMatchesRegularExpression('/^EM-\d{6}$/', (string) $order->number);
        $this->assertSame('Bank transfer', $order->payment_method_name);
        $this->assertCount(2, $order->items);
        $this->assertSame(['billing', 'shipping'], $order->addresses->pluck('type')->sort()->values()->all());

        $this->assertSame(3, (int) InventoryStock::query()->where('variant_id', $mug->id)->value('on_hand'));
        $this->assertSame(2, (int) InventoryStock::query()->where('variant_id', $tee->id)->value('on_hand'));
        $this->assertDatabaseCount('cart_items', 0);

        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'placed' && $m->hasTo('buyer@example.com'));

        $this->get(route('checkout.complete', $order))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('storefront/order-complete')->where('order.number', $order->number));
    }

    public function test_confirmation_page_is_private_to_the_buyer()
    {
        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload());
        $order = Order::query()->firstOrFail();

        $this->flushSession();
        $this->get(route('checkout.complete', $order))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('checkout.complete', $order))->assertNotFound();
    }

    public function test_browser_supplied_amounts_are_ignored_and_changed_totals_are_rejected()
    {
        $variant = $this->sellable('30.00');
        $this->addToCart($variant);

        $this->post(route('checkout.store'), $this->checkoutPayload([
            'grand_total' => '0.01',
            'subtotal' => '0.01',
            'shipping_total' => '0',
            'unit_price' => '0.01',
            'expected_total' => '0.01',
        ]))->assertSessionHasErrors('total');

        $this->assertDatabaseCount('orders', 0);

        $this->post(route('checkout.store'), $this->checkoutPayload(['grand_total' => '0.01', 'expected_total' => '35.00']))
            ->assertSessionHasNoErrors();

        $this->assertSame('35.0000', Order::query()->value('grand_total'));
    }

    public function test_repeated_submission_creates_a_single_order()
    {
        $this->addToCart($this->sellable('10.00', 5), 2);
        $payload = $this->checkoutPayload();

        $this->post(route('checkout.store'), $payload)->assertRedirect();
        $this->post(route('checkout.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_movements', 2); // initial stock + one sale
    }

    public function test_stock_sold_out_during_checkout_is_reported_and_nothing_changes()
    {
        $variant = $this->sellable('10.00', 2);
        $this->addToCart($variant, 2);
        InventoryStock::query()->where('variant_id', $variant->id)->update(['on_hand' => 1]);

        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, (int) InventoryStock::query()->where('variant_id', $variant->id)->value('on_hand'));
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_last_unit_cannot_be_sold_twice()
    {
        $variant = $this->sellable('10.00', 1);

        $this->addToCart($variant);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        // A second shopper had the item in their cart before it sold out.
        $this->flushSession();
        $other = Cart::query()->forceCreate(['token' => (string) Str::uuid(), 'currency' => 'USD']);
        CartItem::query()->forceCreate(['cart_id' => $other->id, 'variant_id' => $variant->id, 'quantity' => 1]);
        $this->withSession(['cart_token' => $other->token])
            ->post(route('checkout.store'), $this->checkoutPayload(['email' => 'second@example.com']))
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(0, (int) InventoryStock::query()->where('variant_id', $variant->id)->value('on_hand'));
    }

    public function test_shipping_and_payment_must_be_valid_choices()
    {
        $this->addToCart($this->sellable());

        $this->post(route('checkout.store'), $this->checkoutPayload(['shipping_method' => 'teleport']))->assertSessionHasErrors('shipping_method');
        $this->post(route('checkout.store'), $this->checkoutPayload(['shipping' => ['country_code' => 'DE']]))->assertSessionHasErrors('cart');
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'stripe']))->assertSessionHasErrors('payment_method');

        app(SettingsRepository::class)->set('payments.bank_transfer.enabled', false);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_free_shipping_threshold_and_inclusive_tax()
    {
        $this->taxRate('US', '12', inclusive: true);
        $this->addToCart($this->sellable('112.00'));

        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $order = Order::query()->firstOrFail();

        // Free over 100; 12% included in 112.00 → tax 12.00, total stays 112.00.
        $this->assertSame('0.0000', $order->shipping_total);
        $this->assertSame('12.0000', $order->tax_total);
        $this->assertSame('112.0000', $order->grand_total);
        $this->assertTrue($order->prices_include_tax);
    }

    public function test_region_specific_tax_rate_wins()
    {
        $this->taxRate('US', '5');
        $this->taxRate('US', '8', region: 'IL');
        $this->addToCart($this->sellable('100.00'));

        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        $this->assertSame('8.0000', Order::query()->value('tax_total'));
    }

    public function test_coupon_discount_is_split_across_lines_and_redeemed()
    {
        $coupon = $this->coupon(['code' => 'TENOFF', 'value' => '10']);
        $this->addToCart($this->sellable('33.33'));
        $this->addToCart($this->sellable('16.67'));
        $this->post(route('cart.coupon.apply'), ['code' => 'tenoff'])->assertSessionHasNoErrors();

        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $order = Order::query()->with('items')->firstOrFail();

        $this->assertSame('5.0000', $order->discount_total);
        $sum = $order->items->reduce(fn (Money $carry, $item) => $carry->plus(Money::of($item->discount_total, 'USD')), Money::zero('USD'));
        $this->assertSame('5.0000', $sum->toDecimal());
        $this->assertSame('TENOFF', $order->coupon_code);
        $this->assertSame(1, $coupon->fresh()?->times_used);
        $this->assertDatabaseHas('coupon_redemptions', ['coupon_id' => $coupon->id, 'email' => 'buyer@example.com']);
    }

    public function test_coupon_rejections_have_clear_reasons()
    {
        $this->addToCart($this->sellable('20.00'));

        $this->coupon(['code' => 'OLD', 'ends_at' => now()->subDay()]);
        $this->post(route('cart.coupon.apply'), ['code' => 'OLD'])->assertSessionHasErrors(['code' => 'This coupon has expired.']);

        $this->coupon(['code' => 'SOON', 'starts_at' => now()->addDay()]);
        $this->post(route('cart.coupon.apply'), ['code' => 'SOON'])->assertSessionHasErrors(['code' => 'This coupon is not active yet.']);

        $this->coupon(['code' => 'BIG', 'min_subtotal' => '50.00']);
        $this->post(route('cart.coupon.apply'), ['code' => 'BIG'])->assertSessionHasErrors('code');

        $this->coupon(['code' => 'USEDUP', 'usage_limit' => 1, 'times_used' => 1]);
        $this->post(route('cart.coupon.apply'), ['code' => 'USEDUP'])->assertSessionHasErrors(['code' => 'This coupon has reached its usage limit.']);

        $category = Category::factory()->create();
        $scoped = $this->coupon(['code' => 'CATONLY', 'applies_to' => 'categories']);
        $scoped->categories()->attach($category);
        $this->post(route('cart.coupon.apply'), ['code' => 'CATONLY'])->assertSessionHasErrors(['code' => 'This coupon does not apply to the items in your cart.']);

        $this->post(route('cart.coupon.apply'), ['code' => 'NOPE'])->assertSessionHasErrors(['code' => 'This coupon code is not valid.']);
    }

    public function test_per_customer_coupon_limit_is_enforced_by_email_at_checkout()
    {
        $this->coupon(['code' => 'ONCE', 'usage_limit_per_customer' => 1]);
        $variant = $this->sellable('20.00', 10);

        $this->addToCart($variant);
        $this->post(route('cart.coupon.apply'), ['code' => 'ONCE']);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        $this->flushSession();
        $this->addToCart($variant);
        $this->post(route('cart.coupon.apply'), ['code' => 'ONCE'])->assertSessionHasNoErrors(); // email unknown in the cart
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasErrors('coupon');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_order_snapshot_survives_catalog_and_address_changes()
    {
        $variant = $this->sellable('15.00', product: ['name' => 'Original name']);
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->addToCart($variant);
        $this->post(route('checkout.store'), $this->checkoutPayload(['save_address' => true]))->assertSessionHasNoErrors();

        $variant->product->update(['name' => 'Renamed']);
        $variant->prices()->update(['amount' => '99.00']);
        $variant->product->delete();
        CustomerAddress::query()->update(['line1' => 'Moved away']);

        $order = Order::query()->with('items', 'shippingAddress')->firstOrFail();
        $this->assertSame('Original name', $order->items->first()?->product_name);
        $this->assertSame('15.0000', $order->items->first()?->unit_price);
        $this->assertSame('1 Main St', $order->shippingAddress?->line1);
    }

    public function test_guest_checkout_can_be_disabled()
    {
        app(SettingsRepository::class)->set('checkout.guest_enabled', false);
        $this->addToCart($this->sellable());

        $this->get(route('checkout.show'))->assertRedirect(route('login'));
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasErrors('email');
    }

    public function test_checkout_page_quotes_shipping_for_the_destination()
    {
        $this->taxRate('US', '10');
        $this->addToCart($this->sellable('40.00'));

        $this->get(route('checkout.show', ['country' => 'US', 'region' => 'IL', 'shipping_method' => 'standard']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/checkout')
                ->where('summary.shipping.code', 'standard')
                ->where('summary.shipping_total.amount', '5.00')
                ->where('summary.tax_total.amount', '4.00')
                ->where('summary.grand_total.amount', '49.00')
                ->has('paymentMethods', 2)
                ->has('checkoutToken'));
    }

    public function test_a_us_address_requires_a_state_province()
    {
        $this->addToCart($this->sellable('20.00'));

        // The country has a known region list, so the region is required.
        $this->post(route('checkout.store'), $this->checkoutPayload([
            'shipping' => ['country_code' => 'US', 'region' => ''],
        ]))->assertSessionHasErrors('shipping.region');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_country_without_a_region_list_does_not_require_one()
    {
        $this->addToCart($this->sellable('20.00'));

        // No region list for the Philippines: the region stays optional.
        $this->post(route('checkout.store'), $this->checkoutPayload([
            'shipping' => ['country_code' => 'PH', 'region' => ''],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_offers_only_the_countries_the_store_ships_to()
    {
        $this->addToCart($this->sellable('20.00'));

        // The fixture method ships to US and PH only.
        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('countries', 2)
                ->where('countries.0.code', 'PH')
                ->where('countries.1.code', 'US')
                ->where('soleCountry', null));
    }

    public function test_a_single_country_store_locks_the_country_field()
    {
        $this->addToCart($this->sellable('20.00'));

        // Narrow coverage to one country.
        ShippingMethod::query()->update(['countries' => ['PH']]);

        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('countries', 1)
                ->where('countries.0.code', 'PH')
                ->where('soleCountry', 'PH'));
    }

    public function test_checkout_asks_for_a_state_or_city_when_the_country_is_already_selected(): void
    {
        ShippingMethod::query()->update([
            'countries' => ['AE'],
            'regions_only' => true,
            'regional_rates' => [
                ['country_code' => 'AE', 'region' => 'Dubai', 'rate' => '15', 'free_over' => null],
            ],
        ]);
        $this->addToCart($this->sellable());

        $this->get(route('checkout.show', ['country' => 'AE']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.shipping_options', [])
                ->where('summary.issues.0', 'Add your state or city to see the shipping method and amount.'));

        $this->get(route('checkout.show', ['country' => 'AE', 'region' => 'Dubai']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.issues', [])
                ->has('summary.shipping_options', 1));

        $this->get(route('checkout.show', ['country' => 'AE', 'region' => 'Ajman']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.issues.0', 'We do not ship to the selected destination.'));
    }
}
