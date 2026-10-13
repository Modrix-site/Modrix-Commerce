<?php

namespace Tests\Feature\Orders;

use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Orders\Enums\DisplayStatus;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class FulfillmentStepsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
        $this->manager = $this->userWithRole('order-manager');
    }

    private function paidOrder(): Order
    {
        $this->addToCart($this->sellable('25.00', 5), 2);
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
        $this->flushSession();
        $order = Order::query()->latest('id')->firstOrFail();
        $this->actingAs($this->manager)->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();

        return $order->refresh();
    }

    private function move(Order $order, string $status, array $extra = [])
    {
        return $this->actingAs($this->manager)->post(route('admin.orders.fulfillment', $order), ['status' => $status, ...$extra]);
    }

    private function stock(Order $order): int
    {
        return (int) InventoryStock::query()->where('variant_id', $order->items()->value('variant_id'))->value('on_hand');
    }

    public function test_the_full_path_in_order()
    {
        $order = $this->paidOrder();

        foreach (['picking', 'packing', 'packed', 'ready_for_dispatch', 'shipped', 'in_transit', 'out_for_delivery', 'delivered'] as $step) {
            $this->move($order, $step, $step === 'shipped' ? ['carrier' => 'DHL', 'tracking_number' => 'T1'] : [])->assertSessionHasNoErrors();
            $this->assertSame($step, $order->fresh()?->fulfillment_status->value, $step);
            $this->assertSame($step, $order->fresh()?->display_status, $step);
        }

        $this->assertSame(OrderStatus::Completed, $order->fresh()?->status);
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'carrier' => 'DHL', 'tracking_number' => 'T1']);
    }

    public function test_the_first_warehouse_step_starts_processing()
    {
        $order = $this->paidOrder();
        $this->assertSame(OrderStatus::Confirmed, $order->status);

        $this->move($order, 'picking')->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Processing, $order->fresh()?->status);
    }

    public function test_steps_can_be_skipped_but_never_go_back()
    {
        $order = $this->paidOrder();

        $this->move($order, 'shipped')->assertSessionHasNoErrors();
        $this->move($order, 'packing')->assertSessionHasErrors('order');
        $this->move($order, 'out_for_delivery')->assertSessionHasNoErrors();
        $this->move($order, 'in_transit')->assertSessionHasErrors('order');
        $this->assertSame(FulfillmentStatus::OutForDelivery, $order->fresh()?->fulfillment_status);
    }

    public function test_carrier_steps_need_a_shipped_order()
    {
        $order = $this->paidOrder();

        $this->move($order, 'in_transit')->assertSessionHasErrors('order');
        $this->move($order, 'delivered')->assertSessionHasErrors('order');
        $this->move($order, 'not_a_status')->assertSessionHasErrors('status');
    }

    public function test_warehouse_steps_need_a_paid_unheld_order()
    {
        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
        $unpaid = Order::query()->latest('id')->firstOrFail();
        $this->move($unpaid, 'picking')->assertSessionHasErrors('order');

        $held = $this->paidOrder();
        $this->post(route('admin.orders.hold', $held))->assertSessionHasNoErrors();
        $this->move($held, 'picking')->assertSessionHasErrors('order');
    }

    public function test_failed_delivery_can_be_retried_and_then_delivered()
    {
        $order = $this->paidOrder();
        $this->move($order, 'shipped')->assertSessionHasNoErrors();
        $this->move($order, 'out_for_delivery')->assertSessionHasNoErrors();

        $this->post(route('admin.orders.delivery-failed', $order), ['reason' => 'Nobody home'])->assertSessionHasNoErrors();
        $this->assertSame(DisplayStatus::DeliveryFailed->value, $order->fresh()?->display_status);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'message' => 'Delivery attempt failed: Nobody home.']);

        $this->move($order, 'out_for_delivery')->assertSessionHasNoErrors();
        $this->move($order, 'delivered')->assertSessionHasNoErrors();
        $this->assertSame(FulfillmentStatus::Delivered, $order->fresh()?->fulfillment_status);
    }

    public function test_delivery_failed_needs_an_order_with_the_carrier()
    {
        $order = $this->paidOrder();

        $this->post(route('admin.orders.delivery-failed', $order), ['reason' => 'x'])->assertSessionHasErrors('order');
    }

    public function test_returns_restock_once()
    {
        $order = $this->paidOrder();
        $before = $this->stock($order);
        $this->move($order, 'shipped')->assertSessionHasNoErrors();
        $this->move($order, 'delivered')->assertSessionHasNoErrors();

        $this->post(route('admin.orders.returned', $order), ['reason' => 'Wrong size', 'restock' => true])->assertSessionHasNoErrors();
        $this->assertSame(DisplayStatus::Returned->value, $order->fresh()?->display_status);
        $this->assertSame($before + 2, $this->stock($order));

        // A second return is refused and never restocks twice.
        $this->post(route('admin.orders.returned', $order), ['reason' => 'Again', 'restock' => true])->assertSessionHasErrors('order');
        $this->assertSame($before + 2, $this->stock($order));
    }

    public function test_returns_without_restock_leave_stock_alone()
    {
        $order = $this->paidOrder();
        $before = $this->stock($order);
        $this->move($order, 'shipped')->assertSessionHasNoErrors();

        $this->post(route('admin.orders.returned', $order), ['reason' => 'Damaged in transit', 'restock' => false])->assertSessionHasNoErrors();
        $this->assertSame($before, $this->stock($order));
        $this->post(route('admin.orders.returned', $this->paidOrder()), ['reason' => 'x'])->assertSessionHasErrors('order');
    }

    public function test_cancel_is_allowed_in_the_warehouse_but_not_after_shipping()
    {
        $packed = $this->paidOrder();
        $this->move($packed, 'ready_for_dispatch')->assertSessionHasNoErrors();
        $this->post(route('admin.orders.refunds.store', $packed), ['amount' => $packed->grand_total, 'reason' => 'Out of stock'])->assertSessionHasNoErrors();
        $this->post(route('admin.orders.cancel', $packed), ['reason' => 'Out of stock'])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Cancelled, $packed->fresh()?->status);

        $shipped = $this->paidOrder();
        $this->move($shipped, 'shipped')->assertSessionHasNoErrors();
        $this->post(route('admin.orders.refunds.store', $shipped), ['amount' => $shipped->grand_total, 'reason' => 'Lost'])->assertSessionHasNoErrors();
        $this->assertNotContains('cancel', app(OrderWorkflow::class)->allowedActions($shipped->fresh()));
        $this->post(route('admin.orders.cancel', $shipped), ['reason' => 'x'])->assertSessionHasErrors('order');
    }

    public function test_allowed_actions_list_the_reachable_steps()
    {
        $order = $this->paidOrder();
        $actions = app(OrderWorkflow::class)->allowedActions($order);
        foreach (['advance:picking', 'advance:packing', 'advance:packed', 'advance:ready_for_dispatch', 'advance:shipped'] as $a) {
            $this->assertContains($a, $actions);
        }
        $this->assertNotContains('advance:in_transit', $actions);

        $this->move($order, 'shipped')->assertSessionHasNoErrors();
        $actions = app(OrderWorkflow::class)->allowedActions($order->fresh());
        $this->assertSame(['advance:in_transit', 'advance:out_for_delivery', 'advance:delivered'], array_values(array_filter($actions, fn ($a) => str_starts_with($a, 'advance:'))));
        $this->assertContains('delivery_failed', $actions);
        $this->assertContains('returned', $actions);
    }

    public function test_fulfilment_requires_orders_manage()
    {
        $order = $this->paidOrder();

        $this->actingAs($this->userWithRole('support-agent'))->post(route('admin.orders.fulfillment', $order), ['status' => 'picking'])->assertForbidden();
    }
}
