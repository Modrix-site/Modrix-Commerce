<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\SettingsRepository;
use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\CheckoutData;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\CartCheckedOut;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderDisplayStatus;
use App\Domains\Pricing\Money;
use App\Domains\Shipping\ShippingRates;
use App\Domains\Shipping\ShippingRequest;
use App\Models\User;
use EcoModular\StorePickup\Listeners\RecordOrderPickup;
use EcoModular\StorePickup\Models\OrderPickup;
use EcoModular\StorePickup\Models\PickupBranch;
use EcoModular\StorePickup\StorePickupCheckoutValidator;
use EcoModular\StorePickup\StorePickupSettings;
use EcoModular\StorePickup\StorePickupShippingRateProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class StorePickupTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/store-pickup';

    private ModuleManager $modules;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules/modrixcommerce/store-pickup'), $this->modulesPath.'/modrixcommerce/store-pickup');
        $this->modules = app(ModuleManager::class);
        $this->modules->install(self::MODULE);
        $this->modules->enable(self::MODULE, true);
        $this->enableCheckout();
        config(['inertia.ssr.enabled' => false]);

        $this->owner = $this->owner();
    }

    private function createBranch(array $attributes = []): PickupBranch
    {
        return PickupBranch::create([
            'name' => $attributes['name'] ?? 'Flagship Store Dubai',
            'code' => $attributes['code'] ?? 'dubai-flagship',
            'address_line1' => $attributes['address_line1'] ?? 'Sheikh Zayed Road, Building 5',
            'city' => $attributes['city'] ?? 'Dubai',
            'region' => $attributes['region'] ?? 'Dubai',
            'country_code' => $attributes['country_code'] ?? 'AE',
            'phone' => $attributes['phone'] ?? '+971 4 000 0000',
            'pickup_hours' => $attributes['pickup_hours'] ?? 'Mon-Sat 10:00 - 20:00',
            'prep_time' => $attributes['prep_time'] ?? 'Ready in 2 hours',
            'fee' => $attributes['fee'] ?? 0.0000,
            'is_active' => $attributes['is_active'] ?? true,
            'order' => $attributes['order'] ?? 0,
        ]);
    }

    private function createOrder(): Order
    {
        return Order::query()->forceCreate([
            'number' => 'ORD-'.str_pad((string) (Order::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'email' => 'customer@example.com',
            'phone' => '+1234567890',
            'currency' => 'AED',
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal' => '200.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '200.0000',
            'paid_total' => '200.0000',
            'shipping_method_code' => StorePickupShippingRateProvider::METHOD_CODE,
            'shipping_method_name' => 'In-Store & Branch Pickup',
            'payment_method_code' => 'manual',
            'payment_method_name' => 'Cash on Pickup',
            'placed_at' => now(),
            'paid_at' => now(),
        ]);
    }

    public function test_module_is_installed_enabled_and_registers_routes(): void
    {
        $ext = Extension::query()->where('name', self::MODULE)->firstOrFail();
        $this->assertSame(ModuleState::Enabled, $ext->state);

        $this->assertTrue(app('router')->has('admin.modrixcommerce.store-pickup.index'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.store-pickup.branches.store'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.store-pickup.branches.update'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.store-pickup.branches.destroy'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.store-pickup.orders.status'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.store-pickup.settings.save'));

        $this->assertTrue(app('router')->has('modrixcommerce.store-pickup.branches'));
        $this->assertTrue(app('router')->has('modrixcommerce.store-pickup.select'));
        $this->assertTrue(app('router')->has('modrixcommerce.store-pickup.clear'));
    }

    public function test_shipping_rate_provider_quotes_pickup_when_branches_exist(): void
    {
        $this->createBranch(['name' => 'Mall of the Emirates Branch', 'fee' => 0.00]);
        $this->createBranch(['name' => 'Dubai Mall Branch', 'code' => 'dubai-mall', 'fee' => 5.00]);

        $rates = app(ShippingRates::class)->quote(new ShippingRequest(
            currency: 'AED',
            countryCode: 'AE',
            region: 'Dubai',
            postalCode: null,
            merchandiseTotal: Money::of('200.00', 'AED'),
            totalWeight: '1.5',
            itemCount: 2,
            city: 'Dubai',
        ));

        $codes = array_column(array_map(fn ($r) => $r->toArray(), $rates), 'code');
        $this->assertContains('store_pickup', $codes);
        $this->assertNotContains('pickup_1', $codes);
        $this->assertNotContains('pickup_2', $codes);

        // When a specific branch with a fee is selected, quote updates fee
        $b2 = PickupBranch::where('code', 'dubai-mall')->firstOrFail();
        session(['store_pickup_selection' => ['branch_id' => $b2->id]]);

        $ratesWithSelection = app(ShippingRates::class)->quote(new ShippingRequest(
            currency: 'AED',
            countryCode: 'AE',
            region: 'Dubai',
            postalCode: null,
            merchandiseTotal: Money::of('200.00', 'AED'),
            totalWeight: '1.5',
            itemCount: 2,
            city: 'Dubai',
        ));
        $pickupRate = collect($ratesWithSelection)->firstWhere('code', 'store_pickup');
        $this->assertNotNull($pickupRate);
        $this->assertSame('5.00', $pickupRate->amount->toDisplayDecimal());
    }

    public function test_storefront_branch_selection_and_clear_apis(): void
    {
        $branch = $this->createBranch(['name' => 'Downtown Hub']);

        // 1. Get branches
        $res = $this->getJson(route('modrixcommerce.store-pickup.branches'));
        $res->assertOk();
        $res->assertJsonPath('branches.0.name', 'Downtown Hub');

        // 2. Select branch
        $selectRes = $this->postJson(route('modrixcommerce.store-pickup.select'), [
            'branch_id' => $branch->id,
            'collector_name' => 'Bob Smith',
            'collector_phone' => '+971501234567',
            'pickup_date' => '2026-11-20',
            'pickup_time' => 'Morning (10:00 AM - 1:00 PM)',
            'customer_notes' => 'Please pack carefully.',
        ]);
        $selectRes->assertOk();
        $selectRes->assertJsonPath('success', true);
        $selectRes->assertJsonPath('selection.branch_id', $branch->id);
        $selectRes->assertJsonPath('selection.collector_name', 'Bob Smith');

        $this->assertSame($branch->id, session('store_pickup_selection.branch_id'));

        // 3. Clear branch
        $clearRes = $this->postJson(route('modrixcommerce.store-pickup.clear'));
        $clearRes->assertOk();
        $this->assertNull(session('store_pickup_selection'));
    }

    public function test_checkout_validator_requires_branch_selection(): void
    {
        $b1 = $this->createBranch(['name' => 'Branch One']);
        $b2 = $this->createBranch(['name' => 'Branch Two', 'code' => 'branch-two']);

        $validator = app(StorePickupCheckoutValidator::class);

        $priced = new PricedCart('AED', []);

        // Without selecting a branch, unified store_pickup should fail validation
        session()->forget('store_pickup_selection');
        $data = new CheckoutData(
            email: 'buyer@example.test',
            phone: '+971500000000',
            shippingAddress: ['country_code' => 'AE', 'city' => 'Dubai', 'first_name' => 'Jane', 'last_name' => 'Doe', 'address1' => 'Street 1'],
            billingAddress: null,
            shippingMethod: 'store_pickup',
            paymentMethod: 'manual',
            customerNote: null,
            idempotencyKey: 'token-123',
            expectedTotal: null,
        );

        $error = $validator->validate($priced, $data);
        $this->assertNotNull($error);
        $this->assertStringContainsString('select a store or branch location', $error);

        // After selecting branch, validation passes
        session(['store_pickup_selection' => ['branch_id' => $b1->id]]);
        $this->assertNull($validator->validate($priced, $data));

        // When using direct branch code pickup_1, validation passes
        $dataDirect = new CheckoutData(
            email: 'buyer@example.test',
            phone: '+971500000000',
            shippingAddress: ['country_code' => 'AE', 'city' => 'Dubai', 'first_name' => 'Jane', 'last_name' => 'Doe', 'address1' => 'Street 1'],
            billingAddress: null,
            shippingMethod: 'pickup_'.$b2->id,
            paymentMethod: 'manual',
            customerNote: null,
            idempotencyKey: 'token-456',
            expectedTotal: null,
        );
        $this->assertNull($validator->validate($priced, $dataDirect));
    }

    public function test_order_placement_records_order_pickup_and_event(): void
    {
        $branch = $this->createBranch(['name' => 'Abu Dhabi Mall Branch']);
        $order = $this->createOrder();

        session(['store_pickup_selection' => [
            'branch_id' => $branch->id,
            'collector_name' => 'Sarah Connor',
            'collector_phone' => '+971559998888',
            'pickup_date' => '2026-11-25',
            'pickup_time' => 'Afternoon (1:00 PM - 5:00 PM)',
            'customer_notes' => 'Fragile items',
        ]]);

        // Dispatch order completion
        CartCheckedOut::dispatch(1, $order->id);

        $this->assertDatabaseHas('order_pickups', [
            'order_id' => $order->id,
            'pickup_branch_id' => $branch->id,
            'branch_name' => 'Abu Dhabi Mall Branch',
            'collector_name' => 'Sarah Connor',
            'status' => 'pending_prep',
        ]);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'status',
        ]);

        // Verify slot resolution on order confirmation
        $summary = app(StorefrontSlots::class)->render('order.confirmation', ['order' => $order]);
        $this->assertNotEmpty($summary);
        $this->assertSame('Abu Dhabi Mall Branch', $summary[0]['props']['branch_name']);
        $this->assertSame('Sarah Connor', $summary[0]['props']['collector_name']);
    }

    public function test_admin_branch_crud_operations(): void
    {
        // 1. Create branch
        $res = $this->actingAs($this->owner)->post(route('admin.modrixcommerce.store-pickup.branches.store'), [
            'name' => 'Marina Walk Express',
            'code' => 'marina-walk',
            'address_line1' => 'Marina Promenade Shop 4',
            'city' => 'Dubai',
            'country_code' => 'AE',
            'phone' => '+97148887777',
            'pickup_hours' => 'Daily 9:00 - 22:00',
            'prep_time' => 'Ready in 1 hour',
            'fee' => 0.00,
            'is_active' => true,
        ]);
        $res->assertRedirect();

        $branch = PickupBranch::where('code', 'marina-walk')->firstOrFail();
        $this->assertSame('Marina Walk Express', $branch->name);

        // 2. Update branch
        $updateRes = $this->actingAs($this->owner)->put(route('admin.modrixcommerce.store-pickup.branches.update', $branch->id), [
            'name' => 'Marina Walk Superstore',
            'code' => 'marina-walk',
            'address_line1' => 'Marina Promenade Shop 4',
            'city' => 'Dubai',
            'country_code' => 'AE',
            'is_active' => true,
            'prep_time' => 'Ready in 30 mins',
        ]);
        $updateRes->assertRedirect();
        $this->assertSame('Marina Walk Superstore', $branch->fresh()->name);
        $this->assertSame('Ready in 30 mins', $branch->fresh()->prep_time);

        // 3. Delete branch
        $delRes = $this->actingAs($this->owner)->delete(route('admin.modrixcommerce.store-pickup.branches.destroy', $branch->id));
        $delRes->assertRedirect();
        $this->assertDatabaseMissing('pickup_branches', ['id' => $branch->id]);
    }

    public function test_admin_can_update_pickup_order_status_and_timestamps(): void
    {
        $branch = $this->createBranch(['name' => 'Al Barsha Hub']);
        $order = $this->createOrder();

        $pickup = OrderPickup::create([
            'order_id' => $order->id,
            'pickup_branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'branch_address' => $branch->fullAddress(),
            'status' => OrderPickup::STATUS_PENDING,
        ]);

        // 1. Mark ready for pickup
        $res = $this->actingAs($this->owner)->post(route('admin.modrixcommerce.store-pickup.orders.status', $pickup->id), [
            'status' => OrderPickup::STATUS_READY,
        ]);
        $res->assertRedirect();

        $pickup->refresh();
        $this->assertSame(OrderPickup::STATUS_READY, $pickup->status);
        $this->assertNotNull($pickup->ready_at);

        $order->refresh();
        $this->assertSame('ready_for_pickup', $order->display_status);
        $this->assertSame('ready_for_pickup', OrderDisplayStatus::forCustomer($order)->value);

        // 2. Mark as picked up
        $res2 = $this->actingAs($this->owner)->post(route('admin.modrixcommerce.store-pickup.orders.status', $pickup->id), [
            'status' => OrderPickup::STATUS_PICKED_UP,
        ]);
        $res2->assertRedirect();

        $pickup->refresh();
        $this->assertSame(OrderPickup::STATUS_PICKED_UP, $pickup->status);
        $this->assertNotNull($pickup->picked_up_at);

        $order->refresh();
        $this->assertSame('picked_up', $order->display_status);
        $this->assertSame('picked_up', OrderDisplayStatus::forCustomer($order)->value);
    }

    public function test_admin_index_renders_pickup_dashboard(): void
    {
        $branch = $this->createBranch(['name' => 'Test Hub']);
        $order = $this->createOrder();
        OrderPickup::create([
            'order_id' => $order->id,
            'pickup_branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'branch_address' => $branch->fullAddress(),
            'status' => OrderPickup::STATUS_PENDING,
        ]);

        $res = $this->actingAs($this->owner)->get(route('admin.modrixcommerce.store-pickup.index'));
        $res->assertOk();
        $res->assertInertia(fn (Assert $page) => $page
            ->component('module/modrixcommerce/store-pickup/admin/index', false)
            ->has('orders.data', 1)
            ->has('branches', 1)
            ->has('settings')
            ->where('counts.total_orders', 1)
            ->where('counts.pending', 1)
        );
    }

    public function test_admin_can_save_settings(): void
    {
        $res = $this->actingAs($this->owner)->post(route('admin.modrixcommerce.store-pickup.settings.save'), [
            'enabled' => true,
            'shipping_label' => 'Free In-Store Collection',
            'allow_alternative_collector' => false,
            'allow_schedule' => true,
            'instructions' => 'Bring order barcode.',
            'cod_behavior' => 'pay_in_store',
            'pay_in_store_label' => 'Pay at Branch Counter',
            'pay_in_store_instructions' => 'Pay via cash or card directly upon collection.',
        ]);
        $res->assertRedirect();

        $settings = app(StorePickupSettings::class);
        $this->assertSame('Free In-Store Collection', $settings->shippingLabel());
        $this->assertFalse($settings->allowAlternativeCollector());
        $this->assertTrue($settings->allowSchedule());
        $this->assertSame('Bring order barcode.', $settings->instructions());
        $this->assertSame('pay_in_store', $settings->codBehavior());
        $this->assertSame('Pay at Branch Counter', $settings->payInStoreLabel());
        $this->assertSame('Pay via cash or card directly upon collection.', $settings->payInStoreInstructions());
    }

    public function test_pricing_step_waives_cod_fee_for_store_pickup(): void
    {
        $this->createBranch(['name' => 'Dubai Flagship', 'fee' => 0.00]);
        $variant = $this->sellable('100.00');
        $this->addToCart($variant);

        $cart = Cart::query()->firstOrFail();
        $this->assertNotNull($cart);

        // Enable Cash on Delivery with fee
        $repo = app(SettingsRepository::class);
        $repo->set('payments.cash_on_delivery.enabled', true);
        $repo->set('payments.cash_on_delivery.fee_enabled', true);
        $repo->set('payments.cash_on_delivery.fee_amount', '10.00');

        $pricer = app(CartPricer::class);

        // 1. When regular delivery is used with COD, fee applies
        $deliveryContext = new PricingContext(
            shippingMethod: 'standard',
            paymentMethod: 'cash_on_delivery',
            countryCode: 'US',
        );
        $pricedDelivery = $pricer->price($cart, $deliveryContext);
        $this->assertSame('10.00', $pricedDelivery->paymentFeeTotal->toDisplayDecimal());

        // 2. When store_pickup is used with COD, fee is waived
        $pickupContext = new PricingContext(
            shippingMethod: 'store_pickup',
            paymentMethod: 'cash_on_delivery',
            countryCode: 'US',
        );
        $pricedPickup = $pricer->price($cart, $pickupContext);
        $this->assertSame('0.00', $pricedPickup->paymentFeeTotal->toDisplayDecimal());
    }

    public function test_checkout_middleware_changes_cod_to_pay_in_store_for_pickup(): void
    {
        $this->createBranch(['name' => 'Dubai Flagship']);
        $variant = $this->sellable('50.00');
        $this->addToCart($variant);

        $repo = app(SettingsRepository::class);
        $repo->set('payments.cash_on_delivery.enabled', true);
        $repo->set('payments.cash_on_delivery.fee_enabled', true);
        $repo->set('payments.cash_on_delivery.fee_amount', '10.00');

        $repo->set('store_pickup.cod_behavior', 'pay_in_store');
        $repo->set('store_pickup.pay_in_store_label', 'Pay in store');
        $repo->set('store_pickup.pay_in_store_instructions', 'Pay at the branch upon collection.');

        // When visiting checkout with store_pickup
        $res = $this->get(route('checkout.show', ['country' => 'US', 'region' => 'IL', 'shipping_method' => 'store_pickup', 'payment_method' => 'cash_on_delivery']));
        $res->assertOk();

        $res->assertInertia(function (Assert $page) {
            $page->component('storefront/checkout', false);
            $methods = $page->toArray()['props']['paymentMethods'];
            $cod = collect($methods)->firstWhere('code', 'cash_on_delivery');
            $this->assertNotNull($cod);
            $this->assertSame('Pay in store', $cod['label']);
            $this->assertSame('Pay at the branch upon collection.', $cod['instructions']);
            $this->assertNull($cod['fee']);
        });
    }

    public function test_checkout_middleware_hides_cod_when_configured(): void
    {
        $this->createBranch(['name' => 'Dubai Flagship']);
        $variant = $this->sellable('50.00');
        $this->addToCart($variant);

        $repo = app(SettingsRepository::class);
        $repo->set('payments.cash_on_delivery.enabled', true);
        $repo->set('payments.bank_transfer.enabled', true);
        $repo->set('store_pickup.cod_behavior', 'hide');

        // When visiting checkout with store_pickup and hide configured
        $res = $this->get(route('checkout.show', ['country' => 'US', 'region' => 'IL', 'shipping_method' => 'store_pickup']));
        $res->assertOk();

        $res->assertInertia(function (Assert $page) {
            $methods = $page->toArray()['props']['paymentMethods'];
            $cod = collect($methods)->firstWhere('code', 'cash_on_delivery');
            $this->assertNull($cod, 'Cash on delivery should be hidden when store pickup is active');
        });
    }

    public function test_delivery_selection_keeps_cash_on_delivery_as_default_with_courier_fee(): void
    {
        $this->createBranch(['name' => 'Dubai Flagship']);
        $variant = $this->sellable('50.00');
        $this->addToCart($variant);

        $repo = app(SettingsRepository::class);
        $repo->set('payments.cash_on_delivery.enabled', true);
        $repo->set('payments.cash_on_delivery.fee_enabled', true);
        $repo->set('payments.cash_on_delivery.fee_amount', '10.00');
        $repo->set('payments.bank_transfer.enabled', true);

        $repo->set('store_pickup.cod_behavior', 'pay_in_store');
        $repo->set('store_pickup.pay_in_store_label', 'Pay in store');

        // When visiting checkout with standard delivery (NOT store_pickup)
        $res = $this->get(route('checkout.show', ['country' => 'US', 'region' => 'IL', 'shipping_method' => 'standard']));
        $res->assertOk();

        $res->assertInertia(function (Assert $page) {
            $methods = $page->toArray()['props']['paymentMethods'];
            // Cash on delivery must be prioritized to index 0 as the default method
            $this->assertSame('cash_on_delivery', $methods[0]['code']);
            $this->assertSame('Cash on delivery', $methods[0]['label']);
            $this->assertNotNull($methods[0]['fee']);
            $this->assertSame('10.00', $methods[0]['fee']['amount']);
        });
    }

    public function test_record_pickup_updates_order_payment_title_to_pay_in_store(): void
    {
        $branch = $this->createBranch(['name' => 'Dubai Mall']);
        session(['store_pickup_selection' => ['branch_id' => $branch->id]]);

        $repo = app(SettingsRepository::class);
        $repo->set('store_pickup.cod_behavior', 'pay_in_store');
        $repo->set('store_pickup.pay_in_store_label', 'Pay in store');

        $order = Order::query()->forceCreate([
            'number' => 'ORD-123456',
            'email' => 'customer@example.com',
            'phone' => '+1234567890',
            'currency' => 'AED',
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal' => '100.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'payment_fee_total' => '10.0000',
            'grand_total' => '110.0000',
            'paid_total' => '0.0000',
            'shipping_method_code' => StorePickupShippingRateProvider::METHOD_CODE,
            'shipping_method_name' => 'In-Store & Branch Pickup',
            'payment_method_code' => 'cash_on_delivery',
            'payment_method_name' => 'Cash on delivery',
            'placed_at' => now(),
        ]);

        $listener = app(RecordOrderPickup::class);
        $listener->handle($order->id);

        $order->refresh();
        $this->assertSame('Pay in store', $order->payment_method_name);
        $this->assertSame('0.0000', $order->payment_fee_total);
        $this->assertSame('100.0000', $order->grand_total);
    }
}
