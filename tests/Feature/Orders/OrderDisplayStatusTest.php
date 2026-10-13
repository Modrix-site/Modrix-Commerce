<?php

namespace Tests\Feature\Orders;

use App\Domains\Orders\Enums\DisplayStatus;
use App\Domains\Orders\Enums\FulfillmentStatus as F;
use App\Domains\Orders\Enums\OrderStatus as O;
use App\Domains\Orders\Enums\PaymentStatus as P;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderDisplayStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class OrderDisplayStatusTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    private function order(O $status, P $payment = P::Unpaid, F $fulfillment = F::Unfulfilled, ?O $beforeHold = null): Order
    {
        return (new Order)->forceFill([
            'status' => $status,
            'payment_status' => $payment,
            'fulfillment_status' => $fulfillment,
            'hold_previous_status' => $beforeHold?->value,
        ]);
    }

    public function test_headline_rules()
    {
        $cases = [
            'cancelled wins' => [$this->order(O::Cancelled, P::Refunded, F::Unfulfilled), DisplayStatus::Cancelled],
            'refunded' => [$this->order(O::Completed, P::Refunded, F::Delivered), DisplayStatus::Refunded],
            'returned' => [$this->order(O::Completed, P::Paid, F::Returned), DisplayStatus::Returned],
            'partially refunded' => [$this->order(O::Completed, P::PartiallyRefunded, F::Delivered), DisplayStatus::PartiallyRefunded],
            'on hold' => [$this->order(O::OnHold, P::Paid, F::Packed, O::Processing), DisplayStatus::OnHold],
            'delivery failed' => [$this->order(O::Processing, P::Paid, F::DeliveryFailed), DisplayStatus::DeliveryFailed],
            'delivered' => [$this->order(O::Completed, P::Paid, F::Delivered), DisplayStatus::Delivered],
            'out for delivery' => [$this->order(O::Processing, P::Paid, F::OutForDelivery), DisplayStatus::OutForDelivery],
            'in transit' => [$this->order(O::Processing, P::Paid, F::InTransit), DisplayStatus::InTransit],
            'shipped' => [$this->order(O::Processing, P::Paid, F::Shipped), DisplayStatus::Shipped],
            'ready for dispatch' => [$this->order(O::Processing, P::Paid, F::ReadyForDispatch), DisplayStatus::ReadyForDispatch],
            'packed' => [$this->order(O::Processing, P::Paid, F::Packed), DisplayStatus::Packed],
            'packing' => [$this->order(O::Processing, P::Paid, F::Packing), DisplayStatus::Packing],
            'picking' => [$this->order(O::Processing, P::Paid, F::Picking), DisplayStatus::Picking],
            'payment failed' => [$this->order(O::PendingPayment, P::Failed), DisplayStatus::PaymentFailed],
            'pending payment' => [$this->order(O::PendingPayment), DisplayStatus::PendingPayment],
            'pending review' => [$this->order(O::Pending), DisplayStatus::Pending],
            'confirmed' => [$this->order(O::Confirmed, P::Paid), DisplayStatus::Confirmed],
            'processing' => [$this->order(O::Processing, P::Paid), DisplayStatus::Processing],
        ];

        foreach ($cases as $name => [$order, $expected]) {
            $this->assertSame($expected, OrderDisplayStatus::for($order), $name);
        }

        $this->assertCount(19, DisplayStatus::cases());
    }

    public function test_customer_never_sees_internal_states()
    {
        $this->assertSame(DisplayStatus::Confirmed, OrderDisplayStatus::forCustomer($this->order(O::OnHold, P::Paid, F::Unfulfilled, O::Confirmed)));
        $this->assertSame(DisplayStatus::PendingPayment, OrderDisplayStatus::forCustomer($this->order(O::OnHold, P::Unpaid, F::Unfulfilled, O::PendingPayment)));

        foreach ([F::Picking, F::Packing, F::Packed, F::ReadyForDispatch] as $step) {
            $this->assertSame(DisplayStatus::Processing, OrderDisplayStatus::forCustomer($this->order(O::Processing, P::Paid, $step)), $step->value);
        }

        $this->assertSame(DisplayStatus::InTransit, OrderDisplayStatus::forCustomer($this->order(O::Processing, P::Paid, F::InTransit)));
    }

    public function test_display_status_is_stored_on_save()
    {
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
        $order = Order::query()->latest('id')->firstOrFail();

        $this->assertSame('pending_payment', DB::table('orders')->where('id', $order->id)->value('display_status'));

        $this->actingAs($this->owner())->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        $this->assertSame('confirmed', DB::table('orders')->where('id', $order->id)->value('display_status'));
    }

    public function test_migration_rollback_leaves_only_values_the_previous_release_knows()
    {
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
        $ids = [];
        foreach (['picking', 'ready_for_dispatch', 'in_transit', 'out_for_delivery', 'delivery_failed', 'returned'] as $f) {
            $this->addToCart($this->sellable());
            $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
            $id = Order::query()->latest('id')->value('id');
            DB::table('orders')->where('id', $id)->update(['fulfillment_status' => $f, 'status' => 'processing', 'payment_status' => $f === 'picking' ? 'failed' : 'paid']);
            $ids[$f] = $id;
        }
        DB::table('orders')->where('id', $ids['picking'])->update(['status' => 'on_hold', 'hold_previous_status' => 'pending']);

        $migration = require database_path('migrations/2026_10_21_000001_detailed_order_statuses.php');
        $migration->down();

        $row = fn (string $f) => DB::table('orders')->where('id', $ids[$f])->first();
        $this->assertSame(['packed', 'unpaid', 'processing'], [$row('picking')->fulfillment_status, $row('picking')->payment_status, $row('picking')->hold_previous_status]);
        $this->assertSame('packed', $row('ready_for_dispatch')->fulfillment_status);
        $this->assertSame('shipped', $row('in_transit')->fulfillment_status);
        $this->assertSame('shipped', $row('out_for_delivery')->fulfillment_status);
        $this->assertSame('shipped', $row('delivery_failed')->fulfillment_status);
        $this->assertSame('delivered', $row('returned')->fulfillment_status);
    }

    public function test_migration_maps_existing_orders()
    {
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
        $make = function () {
            $this->addToCart($this->sellable());
            $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();

            return Order::query()->latest('id')->firstOrFail();
        };
        $unpaid = $make();
        $paid = $make();
        $shipped = $make();
        $held = $make();

        // Rows as the previous release stored them.
        DB::table('orders')->where('id', $unpaid->id)->update(['status' => 'pending', 'display_status' => null]);
        DB::table('orders')->where('id', $paid->id)->update(['status' => 'processing', 'payment_status' => 'paid', 'paid_total' => DB::raw('grand_total'), 'display_status' => null]);
        DB::table('orders')->where('id', $shipped->id)->update(['status' => 'processing', 'payment_status' => 'paid', 'fulfillment_status' => 'shipped', 'display_status' => null]);
        DB::table('orders')->where('id', $held->id)->update(['status' => 'on_hold', 'hold_previous_status' => 'processing', 'payment_status' => 'paid', 'display_status' => null]);

        $migration = require database_path('migrations/2026_10_21_000001_detailed_order_statuses.php');
        $migration->up();

        $row = fn (Order $o) => DB::table('orders')->where('id', $o->id)->first(['status', 'display_status', 'hold_previous_status']);
        $this->assertSame(['pending_payment', 'pending_payment'], [$row($unpaid)->status, $row($unpaid)->display_status]);
        $this->assertSame(['confirmed', 'confirmed'], [$row($paid)->status, $row($paid)->display_status]);
        $this->assertSame(['processing', 'shipped'], [$row($shipped)->status, $row($shipped)->display_status]);
        $this->assertSame(['on_hold', 'confirmed'], [$row($held)->status, $row($held)->hold_previous_status]);

        // Old orders keep working: the unpaid one can be paid, the paid one shipped.
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('admin.orders.payments.store', $unpaid))->assertSessionHasNoErrors();
        $this->post(route('admin.orders.ship', $paid))->assertSessionHasNoErrors();
    }
}
