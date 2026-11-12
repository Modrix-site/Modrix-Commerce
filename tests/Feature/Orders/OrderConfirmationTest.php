<?php

namespace Tests\Feature\Orders;

use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class OrderConfirmationTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
    }

    private function place(string $method): Order
    {
        $this->addToCart($this->sellable('25.00', 5));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => $method]))->assertSessionHasNoErrors();
        $this->flushSession();

        return Order::query()->latest('id')->firstOrFail();
    }

    public function test_paid_orders_are_confirmed_automatically()
    {
        $order = $this->place('bank_transfer');
        $this->assertSame(OrderStatus::PendingPayment, $order->status);

        $this->actingAs($this->owner())->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()?->status);
        $this->assertNotContains('confirm', app(OrderWorkflow::class)->allowedActions($order->fresh()));
    }

    public function test_cash_on_delivery_orders_are_confirmed_when_placed()
    {
        $this->assertSame(OrderStatus::Confirmed, $this->place('cash_on_delivery')->status);
    }

    public function test_review_setting_holds_orders_in_pending_until_staff_confirm()
    {
        app(SettingsRepository::class)->set('orders.review_before_confirm', true);
        $cod = $this->place('cash_on_delivery');
        $this->assertSame(OrderStatus::Pending, $cod->status);

        $manager = $this->userWithRole('order-manager');
        $actions = app(OrderWorkflow::class)->allowedActions($cod);
        $this->assertContains('confirm', $actions);
        $this->assertNotContains('ship', $actions);
        $this->actingAs($manager)->post(route('admin.orders.ship', $cod))->assertSessionHasErrors('order');

        $this->post(route('admin.orders.confirm', $cod))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Confirmed, $cod->fresh()?->status);
        $this->assertContains('ship', app(OrderWorkflow::class)->allowedActions($cod->fresh()));

        // A paid order also waits for review.
        $bank = $this->place('bank_transfer');
        $this->actingAs($manager)->post(route('admin.orders.payments.store', $bank))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Pending, $bank->fresh()?->status);
    }

    public function test_paying_a_confirmed_order_keeps_it_confirmed()
    {
        app(SettingsRepository::class)->set('orders.review_before_confirm', true);
        $cod = $this->place('cash_on_delivery');
        $manager = $this->userWithRole('order-manager');
        $this->actingAs($manager)->post(route('admin.orders.confirm', $cod))->assertSessionHasNoErrors();

        $this->post(route('admin.orders.payments.store', $cod))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Confirmed, $cod->fresh()?->status);

        // Same when the order was held while confirmed.
        $held = $this->place('cash_on_delivery');
        $this->post(route('admin.orders.confirm', $held))->assertSessionHasNoErrors();
        $this->post(route('admin.orders.hold', $held))->assertSessionHasNoErrors();
        $this->post(route('admin.orders.payments.store', $held))->assertSessionHasNoErrors();
        $this->post(route('admin.orders.release', $held))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Confirmed, $held->fresh()?->status);
    }

    public function test_confirm_is_refused_unless_pending_review()
    {
        $order = $this->place('bank_transfer');

        $this->actingAs($this->userWithRole('order-manager'))->post(route('admin.orders.confirm', $order))->assertSessionHasErrors('order');
        $this->actingAs($this->userWithRole('support-agent'))->post(route('admin.orders.confirm', $order))->assertForbidden();
    }
}
