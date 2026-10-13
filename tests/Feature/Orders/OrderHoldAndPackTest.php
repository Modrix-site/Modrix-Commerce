<?php

namespace Tests\Feature\Orders;

use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class OrderHoldAndPackTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
    }

    private function placeOrder(string $payment = 'bank_transfer', ?User $customer = null): Order
    {
        if ($customer !== null) {
            $this->actingAs($customer);
        }

        $this->addToCart($this->sellable('25.00', 5), 2);
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => $payment]))->assertSessionHasNoErrors();
        $this->flushSession();
        auth()->logout();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function paidOrder(): Order
    {
        $order = $this->placeOrder();
        $this->actingAs($this->userWithRole('order-manager'))->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();

        return $order->refresh();
    }

    public function test_hold_blocks_shipping_and_release_restores_status()
    {
        $order = $this->paidOrder();
        $manager = $this->userWithRole('order-manager');

        $this->actingAs($manager)->post(route('admin.orders.hold', $order), ['reason' => 'Address check'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(OrderStatus::OnHold, $order->status);
        $actions = app(OrderWorkflow::class)->allowedActions($order);
        $this->assertNotContains('ship', $actions);
        $this->assertNotContains('mark_packed', $actions);
        $this->assertContains('release', $actions);
        $this->assertNotContains('hold', $actions);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'type' => 'held', 'message' => 'Order put on hold: Address check.', 'is_customer_visible' => false]);

        $this->post(route('admin.orders.ship', $order))->assertSessionHasErrors('order');

        $this->post(route('admin.orders.release', $order))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertNull($order->hold_previous_status);
        $this->assertContains('ship', app(OrderWorkflow::class)->allowedActions($order));
    }

    public function test_payment_while_on_hold_stays_on_hold_and_release_goes_to_processing()
    {
        $order = $this->placeOrder();
        $manager = $this->userWithRole('order-manager');

        $this->actingAs($manager)->post(route('admin.orders.hold', $order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::OnHold, $order->fresh()?->status);

        $this->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(OrderStatus::OnHold, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);

        $this->post(route('admin.orders.release', $order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Processing, $order->fresh()?->status);
    }

    public function test_pack_then_ship()
    {
        $order = $this->paidOrder();

        $this->post(route('admin.orders.pack', $order))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(FulfillmentStatus::Packed, $order->fulfillment_status);
        $this->assertNotContains('mark_packed', app(OrderWorkflow::class)->allowedActions($order));
        $this->assertContains('hold', app(OrderWorkflow::class)->allowedActions($order));

        $this->post(route('admin.orders.ship', $order), ['carrier' => 'LBC'])->assertSessionHasNoErrors();
        $this->assertSame(FulfillmentStatus::Shipped, $order->fresh()?->fulfillment_status);
    }

    public function test_pack_not_allowed_when_on_hold_or_cancelled()
    {
        $order = $this->paidOrder();
        $this->post(route('admin.orders.hold', $order))->assertSessionHasNoErrors();
        $this->post(route('admin.orders.pack', $order))->assertSessionHasErrors('order');

        $unpaid = $this->placeOrder();
        $this->actingAs($this->userWithRole('order-manager'))->post(route('admin.orders.cancel', $unpaid), ['reason' => 'Customer asked'])->assertSessionHasNoErrors();
        $this->assertNotContains('mark_packed', app(OrderWorkflow::class)->allowedActions($unpaid->refresh()));
        $this->post(route('admin.orders.pack', $unpaid))->assertSessionHasErrors('order');
    }

    public function test_cancel_while_on_hold_restocks()
    {
        $order = $this->placeOrder();
        $variantId = $order->items()->value('variant_id');
        $before = (int) InventoryStock::query()->where('variant_id', $variantId)->value('on_hand');

        $this->actingAs($this->userWithRole('order-manager'))->post(route('admin.orders.hold', $order))->assertSessionHasNoErrors();
        $this->post(route('admin.orders.cancel', $order), ['reason' => 'Fraud check failed'])->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()?->status);
        $this->assertSame($before + 2, (int) InventoryStock::query()->where('variant_id', $variantId)->value('on_hand'));
    }

    public function test_notify_sends_update_mail_only_when_ticked()
    {
        $order = $this->paidOrder();

        $this->post(route('admin.orders.hold', $order), ['reason' => 'We are checking your address.', 'notify' => true])->assertSessionHasNoErrors();
        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'updated' && $m->note === 'We are checking your address.');
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'message' => 'We are checking your address.', 'is_customer_visible' => true]);

        $this->post(route('admin.orders.release', $order))->assertSessionHasNoErrors();
        $this->post(route('admin.orders.pack', $order), ['notify' => false])->assertSessionHasNoErrors();
        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'updated', 1);

        // Shipping without notify suppresses the shipped email.
        $this->post(route('admin.orders.ship', $order), ['notify' => false])->assertSessionHasNoErrors();
        Mail::assertNotSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'shipped');
    }

    public function test_customer_never_sees_on_hold()
    {
        $customer = User::factory()->create();
        $order = $this->placeOrder(customer: $customer);
        $this->actingAs($this->userWithRole('order-manager'))->post(route('admin.orders.hold', $order), ['reason' => 'Internal fraud review'])->assertSessionHasNoErrors();

        $this->actingAs($customer)->get(route('account.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.status', 'pending')
                ->where('order.history', fn ($history) => collect($history)->doesntContain(fn ($e) => str_contains($e['message'], 'fraud'))));

        $this->get(route('account.orders'))
            ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.status', 'pending'));

        // Once paid, the held order shows as processing.
        $this->actingAs($this->userWithRole('order-manager'))->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::OnHold, $order->fresh()?->status);
        $this->actingAs($customer)->get(route('account.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('order.status', 'processing'));
    }

    public function test_hold_requires_orders_manage()
    {
        $order = $this->paidOrder();
        $viewer = $this->userWithRole('support-agent'); // orders.view only

        $this->actingAs($viewer)->post(route('admin.orders.hold', $order))->assertForbidden();
        $this->post(route('admin.orders.pack', $order))->assertForbidden();
        $this->post(route('admin.orders.release', $order))->assertForbidden();
    }
}
