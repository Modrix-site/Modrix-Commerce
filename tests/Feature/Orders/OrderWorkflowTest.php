<?php

namespace Tests\Feature\Orders;

use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
    }

    private function placeOrder(string $payment = 'bank_transfer', int $stock = 5, int $quantity = 2, ?string $coupon = null): Order
    {
        $variant = $this->sellable('25.00', $stock);
        $this->addToCart($variant, $quantity);

        if ($coupon !== null) {
            $this->post(route('cart.coupon.apply'), ['code' => $coupon])->assertSessionHasNoErrors();
        }

        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => $payment]))->assertSessionHasNoErrors();
        $this->flushSession();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function stockOf(Order $order): int
    {
        return (int) InventoryStock::query()->where('variant_id', $order->items()->value('variant_id'))->value('on_hand');
    }

    public function test_bank_transfer_lifecycle_payment_ship_deliver_complete()
    {
        $order = $this->placeOrder();
        $manager = $this->userWithRole('order-manager');

        // Bank transfer orders cannot ship before payment.
        $this->actingAs($manager)->post(route('admin.orders.ship', $order))->assertSessionHasErrors('order');

        $this->actingAs($manager)->post(route('admin.orders.payments.store', $order), ['reference' => 'TX-1'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame($order->grand_total, $order->paid_total);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'provider' => 'manual', 'provider_reference' => 'TX-1', 'recorded_by' => $manager->id]);

        // Recording twice is refused.
        $this->actingAs($manager)->post(route('admin.orders.payments.store', $order))->assertSessionHasErrors('order');

        $this->actingAs($manager)->post(route('admin.orders.ship', $order), ['carrier' => 'LBC', 'tracking_number' => 'TRK123'])->assertSessionHasNoErrors();
        $this->assertSame(FulfillmentStatus::Shipped, $order->fresh()?->fulfillment_status);
        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'shipped');

        $this->actingAs($manager)->post(route('admin.orders.deliver', $order))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);

        // Shipped orders cannot be cancelled.
        $this->actingAs($manager)->post(route('admin.orders.cancel', $order), ['reason' => 'x'])->assertSessionHasErrors('order');
    }

    public function test_cash_on_delivery_can_ship_before_payment_and_completes_when_paid()
    {
        $order = $this->placeOrder('cash_on_delivery');
        $manager = $this->userWithRole('order-manager');

        $this->actingAs($manager)->post(route('admin.orders.ship', $order))->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('admin.orders.deliver', $order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Processing, $order->fresh()?->status);

        $this->actingAs($manager)->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Completed, $order->fresh()?->status);
    }

    public function test_cancelling_returns_stock_and_releases_the_coupon()
    {
        $coupon = $this->coupon(['code' => 'CANCELME']);
        $order = $this->placeOrder(stock: 5, quantity: 2, coupon: 'CANCELME');
        $this->assertSame(3, $this->stockOf($order));
        $this->assertSame(1, $coupon->fresh()?->times_used);

        $this->actingAs($this->userWithRole('order-manager'))
            ->post(route('admin.orders.cancel', $order), ['reason' => 'Customer request'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(5, $this->stockOf($order));
        $this->assertSame(0, $coupon->fresh()?->times_used);
        $this->assertDatabaseHas('inventory_movements', ['reason' => 'cancellation', 'quantity_change' => 2]);
        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'cancelled');
    }

    public function test_paid_orders_must_be_refunded_before_cancelling()
    {
        $order = $this->placeOrder();
        $manager = $this->userWithRole('store-manager');
        $this->actingAs($manager)->post(route('admin.orders.payments.store', $order));

        $this->actingAs($manager)->post(route('admin.orders.cancel', $order), ['reason' => 'x'])->assertSessionHasErrors('order');

        $this->actingAs($manager)->post(route('admin.orders.refunds.store', $order), ['amount' => '55.00', 'reason' => 'Full refund'])->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Refunded, $order->fresh()?->payment_status);

        $this->actingAs($manager)->post(route('admin.orders.cancel', $order), ['reason' => 'Refunded'])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()?->status);
    }

    public function test_partial_refunds_with_restock_and_limits()
    {
        $order = $this->placeOrder(stock: 5, quantity: 2);
        $manager = $this->userWithRole('store-manager');
        $this->actingAs($manager)->post(route('admin.orders.payments.store', $order));
        $item = $order->items()->firstOrFail();

        // 2 × 25.00 + 5.00 shipping = 55.00 paid.
        $this->actingAs($manager)->post(route('admin.orders.refunds.store', $order), [
            'amount' => '25.00', 'reason' => 'One damaged', 'restock' => [$item->id => 1],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->payment_status);
        $this->assertSame('25.0000', $order->refunded_total);
        $this->assertSame(4, $this->stockOf($order));
        $this->assertSame(1, $item->fresh()?->restocked_quantity);

        $this->actingAs($manager)->post(route('admin.orders.refunds.store', $order), ['amount' => '30.01', 'reason' => 'Too much'])->assertSessionHasErrors('amount');
        $this->actingAs($manager)->post(route('admin.orders.refunds.store', $order), [
            'amount' => '1.00', 'reason' => 'Over-restock', 'restock' => [$item->id => 2],
        ])->assertSessionHasErrors('restock');
        $this->assertSame('25.0000', $order->fresh()?->refunded_total);
    }

    public function test_permissions_on_order_actions()
    {
        $order = $this->placeOrder();

        $this->actingAs($this->userWithRole('support-agent'))->get(route('admin.orders.show', $order))->assertOk();
        $this->actingAs($this->userWithRole('support-agent'))->post(route('admin.orders.payments.store', $order))->assertForbidden();
        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('analyst'))->post(route('admin.orders.refunds.store', $order), ['amount' => '1', 'reason' => 'x'])->assertForbidden();

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()?->payment_status);
    }

    public function test_order_history_is_immutable_and_notes_respect_visibility()
    {
        $order = $this->placeOrder();
        $manager = $this->userWithRole('order-manager');

        $this->actingAs($manager)->post(route('admin.orders.notes.store', $order), ['message' => 'Internal: fraud check passed'])->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('admin.orders.notes.store', $order), ['message' => 'Your parcel is packed', 'customer_visible' => true]);

        $event = OrderEvent::query()->where('message', 'like', 'Internal%')->firstOrFail();
        $this->assertFalse($event->is_customer_visible);

        $this->expectException(LogicException::class);
        $event->forceFill(['message' => 'tampered'])->save();
    }

    public function test_order_emails_are_not_sent_twice_for_duplicate_events()
    {
        $order = $this->placeOrder();
        $this->actingAs($this->userWithRole('order-manager'))->post(route('admin.orders.payments.store', $order));

        OrderPaid::dispatch($order->id); // a redelivered event

        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'paid', 1);
        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'placed', 1);
    }

    public function test_admin_order_pages_render_with_allowed_actions()
    {
        $order = $this->placeOrder();

        $this->actingAs($this->userWithRole('order-manager'))
            ->get(route('admin.orders.show', $order))
            ->assertInertia(fn ($page) => $page
                ->component('admin/orders/show')
                ->where('actions', ['record_payment', 'hold', 'cancel'])
                ->where('order.number', $order->number));

        $this->actingAs($this->owner())
            ->get(route('admin.orders.index', ['search' => $order->number]))
            ->assertInertia(fn ($page) => $page->component('admin/orders/index')->has('orders.data', 1));
    }
}
