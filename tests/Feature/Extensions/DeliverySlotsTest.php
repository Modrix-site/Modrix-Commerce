<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Domains\Cart\Models\Cart;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\CartCheckedOut;
use App\Domains\Orders\Models\Order;
use Carbon\Carbon;
use EcoModular\DeliverySlots\DeliverySlotCheckoutValidator;
use EcoModular\DeliverySlots\DeliverySlotResolver;
use EcoModular\DeliverySlots\DeliverySlotsSettings;
use EcoModular\DeliverySlots\Models\DeliveryBlackoutDate;
use EcoModular\DeliverySlots\Models\DeliverySlot;
use EcoModular\DeliverySlots\Models\OrderDeliverySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class DeliverySlotsTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/delivery-slots';

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

    private function createTestOrder(): Order
    {
        return Order::query()->forceCreate([
            'number' => 'ORD-' . str_pad((string) (Order::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'email' => 'customer@example.com',
            'phone' => '+1234567890',
            'currency' => 'USD',
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal' => '150.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '150.0000',
            'paid_total' => '150.0000',
            'payment_method_code' => 'manual',
            'payment_method_name' => 'Cash on Delivery',
            'placed_at' => now(),
            'paid_at' => now(),
        ]);
    }

    public function test_module_is_enabled_and_registers_routes_and_services(): void
    {
        $this->assertSame(ModuleState::Enabled, $this->state(self::MODULE));

        $this->assertTrue(app('router')->has('admin.modrixcommerce.delivery-slots.index'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.delivery-slots.slots'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.delivery-slots.slots.store'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.delivery-slots.settings'));
        $this->assertTrue(app('router')->has('modrixcommerce.delivery-slots.select'));
        $this->assertTrue(app('router')->has('modrixcommerce.delivery-slots.slots-for-date'));
        $this->assertTrue(app('router')->has('modrixcommerce.delivery-slots.clear'));

        $this->assertInstanceOf(DeliverySlotsSettings::class, app(DeliverySlotsSettings::class));
        $this->assertInstanceOf(DeliverySlotResolver::class, app(DeliverySlotResolver::class));
        $this->assertInstanceOf(DeliverySlotCheckoutValidator::class, app(DeliverySlotCheckoutValidator::class));
    }

    public function test_admin_can_view_delivery_schedule_page(): void
    {
        $slot = DeliverySlot::create([
            'name' => 'Morning Window',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'cutoff_hours' => 2,
            'max_orders' => 15,
            'fee' => 0,
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $order = $this->createTestOrder();

        OrderDeliverySchedule::create([
            'order_id' => $order->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->toDateString(),
            'slot_name' => 'Morning Window',
            'delivery_instructions' => 'Gate code #1234',
        ]);

        $response = $this->actingAs($this->owner())->get(route('admin.modrixcommerce.delivery-slots.index'));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('module/modrixcommerce/delivery-slots/admin/index', false)
            ->has('schedules', 1)
            ->where('schedules.0.order_id', $order->id)
            ->where('schedules.0.delivery_instructions', 'Gate code #1234')
            ->has('stats')
        );
    }

    public function test_admin_can_manage_delivery_slots_crud(): void
    {
        // 1. Create Slot
        $createResponse = $this->actingAs($this->owner())->post(route('admin.modrixcommerce.delivery-slots.slots.store'), [
            'name' => 'Evening Rush',
            'start_time' => '17:00',
            'end_time' => '21:00',
            'cutoff_hours' => 3,
            'max_orders' => 30,
            'fee' => 5.50,
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_active' => true,
            'order' => 1,
        ]);

        $createResponse->assertRedirect(route('admin.modrixcommerce.delivery-slots.slots'));
        $slot = DeliverySlot::query()->where('name', 'Evening Rush')->first();
        $this->assertNotNull($slot);
        $this->assertSame(3, $slot->cutoff_hours);
        $this->assertSame(30, $slot->max_orders);

        // 2. Toggle Slot
        $this->actingAs($this->owner())->post(route('admin.modrixcommerce.delivery-slots.slots.toggle', $slot));
        $this->assertFalse($slot->fresh()->is_active);

        // 3. Update Slot
        $this->actingAs($this->owner())->put(route('admin.modrixcommerce.delivery-slots.slots.update', $slot), [
            'name' => 'Evening Express Window',
            'start_time' => '18:00',
            'end_time' => '22:00',
            'cutoff_hours' => 2,
            'max_orders' => 40,
            'fee' => 7.00,
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);
        $this->assertSame('Evening Express Window', $slot->fresh()->name);
        $this->assertSame(40, $slot->fresh()->max_orders);

        // 4. Delete Slot
        $this->actingAs($this->owner())->delete(route('admin.modrixcommerce.delivery-slots.slots.delete', $slot));
        $this->assertNull(DeliverySlot::query()->find($slot->id));
    }

    public function test_admin_can_manage_blackout_dates_and_settings(): void
    {
        // 1. Save Settings
        $settingsResponse = $this->actingAs($this->owner())->put(route('admin.modrixcommerce.delivery-slots.settings.update'), [
            'enabled' => true,
            'is_required' => true,
            'max_advance_days' => 14,
            'same_day_cutoff' => '13:30',
            'heading' => 'Choose When Your Box Arrives',
        ]);
        $settingsResponse->assertRedirect(route('admin.modrixcommerce.delivery-slots.settings'));

        /** @var DeliverySlotsSettings $settings */
        $settings = app(DeliverySlotsSettings::class);
        $this->assertTrue($settings->isRequired());
        $this->assertSame(14, $settings->maxAdvanceDays());
        $this->assertSame('13:30', $settings->sameDayCutoff());
        $this->assertSame('Choose When Your Box Arrives', $settings->heading());

        // 2. Add Blackout Date
        $blackoutDate = now()->addDays(3)->toDateString();
        $this->actingAs($this->owner())->post(route('admin.modrixcommerce.delivery-slots.blackouts.store'), [
            'date' => $blackoutDate,
            'reason' => 'Public Holiday',
        ]);

        $this->assertTrue(DeliveryBlackoutDate::isBlackedOut($blackoutDate));
        $blackout = DeliveryBlackoutDate::query()->whereDate('date', $blackoutDate)->first();

        // 3. Delete Blackout Date
        $this->actingAs($this->owner())->delete(route('admin.modrixcommerce.delivery-slots.blackouts.delete', $blackout));
        $this->assertFalse(DeliveryBlackoutDate::isBlackedOut($blackoutDate));
    }

    public function test_storefront_can_fetch_and_select_slot(): void
    {
        $slot = DeliverySlot::create([
            'name' => 'Afternoon Delivery',
            'start_time' => '13:00',
            'end_time' => '17:00',
            'cutoff_hours' => 1,
            'max_orders' => 10,
            'fee' => 0,
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $targetDate = now()->addDays(2)->toDateString();

        // 1. Fetch available slots for date
        $fetchResponse = $this->getJson(route('modrixcommerce.delivery-slots.slots-for-date', ['date' => $targetDate]));
        $fetchResponse->assertOk();
        $fetchResponse->assertJsonFragment(['name' => 'Afternoon Delivery']);

        // 2. Select slot
        $selectResponse = $this->postJson(route('modrixcommerce.delivery-slots.select'), [
            'date' => $targetDate,
            'delivery_slot_id' => $slot->id,
            'delivery_instructions' => 'Call mobile before ringing doorbell',
        ]);

        $selectResponse->assertOk();
        $selectResponse->assertJson(['success' => true]);

        $this->assertSame($targetDate, session('delivery_slots_selection.date'));
        $this->assertSame($slot->id, session('delivery_slots_selection.delivery_slot_id'));
        $this->assertSame('Call mobile before ringing doorbell', session('delivery_slots_selection.instructions'));

        // 3. Clear slot
        $this->postJson(route('modrixcommerce.delivery-slots.clear'))->assertOk();
        $this->assertNull(session('delivery_slots_selection'));
    }

    public function test_checkout_validator_blocks_order_when_slot_is_required_and_missing(): void
    {
        /** @var DeliverySlotsSettings $settings */
        $settings = app(DeliverySlotsSettings::class);
        $settings->save([
            'enabled' => true,
            'is_required' => true,
        ]);

        session()->forget('delivery_slots_selection');

        $validator = app(DeliverySlotCheckoutValidator::class);
        $cart = $this->createPricedCartMock();
        $checkoutData = $this->createCheckoutDataMock();

        $error = $validator->validate($cart, $checkoutData);
        $this->assertNotNull($error);
        $this->assertStringContainsString('Please select a preferred delivery date', $error);

        // Now select a valid slot
        $slot = DeliverySlot::create([
            'name' => 'Standard Window',
            'start_time' => '10:00',
            'end_time' => '14:00',
            'cutoff_hours' => 1,
            'max_orders' => 50,
            'fee' => 0,
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $date = now()->addDay()->toDateString();
        session()->put('delivery_slots_selection', [
            'date' => $date,
            'delivery_slot_id' => $slot->id,
            'slot_name' => $slot->name,
        ]);

        $this->assertNull($validator->validate($cart, $checkoutData));
    }

    public function test_order_completion_records_delivery_schedule(): void
    {
        $slot = DeliverySlot::create([
            'name' => 'Priority Slot',
            'start_time' => '08:00',
            'end_time' => '11:00',
            'cutoff_hours' => 2,
            'max_orders' => 20,
            'fee' => 0,
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $order = $this->createTestOrder();
        $date = now()->addDays(2)->toDateString();

        session()->put('delivery_slots_selection', [
            'date' => $date,
            'delivery_slot_id' => $slot->id,
            'slot_name' => $slot->name,
            'instructions' => 'Leave near back porch',
        ]);

        // Dispatch CartCheckedOut or OrderPlaced
        CartCheckedOut::dispatch(1, $order->id);

        $schedule = OrderDeliverySchedule::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($schedule);
        $this->assertSame($slot->id, $schedule->delivery_slot_id);
        $this->assertSame($date, $schedule->delivery_date->toDateString());
        $this->assertSame('Leave near back porch', $schedule->delivery_instructions);

        // Session selection was cleaned up
        $this->assertNull(session('delivery_slots_selection'));
    }

    private function createPricedCartMock(): \App\Domains\Checkout\Pricing\PricedCart
    {
        return new \App\Domains\Checkout\Pricing\PricedCart('USD', []);
    }

    private function createCheckoutDataMock(): \App\Domains\Checkout\CheckoutData
    {
        return new \App\Domains\Checkout\CheckoutData(
            email: 'shopper@example.com',
            phone: '+1234567890',
            shippingAddress: ['first_name' => 'Jane', 'last_name' => 'Doe', 'country_code' => 'US', 'line_1' => '123 Main St', 'city' => 'City', 'postal_code' => '12345'],
            billingAddress: ['first_name' => 'Jane', 'last_name' => 'Doe', 'country_code' => 'US', 'line_1' => '123 Main St', 'city' => 'City', 'postal_code' => '12345'],
            shippingMethod: 'standard',
            paymentMethod: 'manual',
            customerNote: null,
            idempotencyKey: 'test-idem-key',
            expectedTotal: '0.00',
            saveAddress: false,
        );
    }
}
