<?php

namespace Tests\Feature\Orders;

use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Documents\DocumentSettings;
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class StatusEmailTest extends TestCase
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

    private function shippedOrder(): Order
    {
        $this->addToCart($this->sellable('25.00', 5));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
        $this->flushSession();
        $order = Order::query()->latest('id')->firstOrFail();
        $this->actingAs($this->manager)->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        $this->post(route('admin.orders.fulfillment', $order), ['status' => 'shipped'])->assertSessionHasNoErrors();

        return $order->refresh();
    }

    private function sent(string $kind): int
    {
        return Mail::sent(OrderMail::class, fn (OrderMail $m) => $m->kind === $kind)->count();
    }

    public function test_status_emails_are_sent_by_default()
    {
        $order = $this->shippedOrder();
        $this->assertSame(1, $this->sent('shipped'));

        $this->post(route('admin.orders.fulfillment', $order), ['status' => 'out_for_delivery'])->assertSessionHasNoErrors();
        $this->post(route('admin.orders.delivery-failed', $order), ['reason' => 'Nobody home'])->assertSessionHasNoErrors();
        $this->post(route('admin.orders.fulfillment', $order), ['status' => 'delivered'])->assertSessionHasNoErrors();
        $this->post(route('admin.orders.returned', $order), ['reason' => 'Wrong size'])->assertSessionHasNoErrors();

        foreach (['out_for_delivery', 'delivery_failed', 'delivered', 'returned'] as $kind) {
            $this->assertSame(1, $this->sent($kind), $kind);
        }
        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'delivery_failed' && $m->note === 'Delivery attempt failed: Nobody home.');
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'type' => 'email', 'message' => 'Email sent to customer: Your order was delivered.']);
    }

    public function test_switched_off_emails_are_not_sent_unless_ticked()
    {
        app(SettingsRepository::class)->setMany(['orders.notify.shipped' => false, 'orders.notify.out_for_delivery' => false]);
        $order = $this->shippedOrder();
        $this->assertSame(0, $this->sent('shipped'));

        $this->post(route('admin.orders.fulfillment', $order), ['status' => 'out_for_delivery'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->sent('out_for_delivery'));

        // The tick box overrides the setting for one change, both ways.
        $this->post(route('admin.orders.delivery-failed', $order), ['reason' => 'x', 'notify' => false])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->sent('delivery_failed'));
        $this->post(route('admin.orders.fulfillment', $order), ['status' => 'out_for_delivery', 'notify' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->sent('out_for_delivery'));
    }

    public function test_confirmation_email_only_when_staff_confirm_a_reviewed_order()
    {
        app(SettingsRepository::class)->set('orders.review_before_confirm', true);
        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'cash_on_delivery']))->assertSessionHasNoErrors();
        $order = Order::query()->latest('id')->firstOrFail();

        $this->actingAs($this->manager)->post(route('admin.orders.confirm', $order))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->sent('confirmed'));
    }

    public function test_payment_failed_email()
    {
        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
        $order = Order::query()->latest('id')->firstOrFail();
        $payment = new Payment;
        $payment->forceFill(['order_id' => $order->id, 'method_code' => 'stripe', 'provider' => 'stripe', 'amount' => $order->grand_total, 'currency' => $order->currency, 'status' => Payment::STATUS_PENDING])->save();

        app(OrderWorkflow::class)->failGatewayPayment($payment, 'Card declined');

        $this->assertSame(1, $this->sent('payment_failed'));

        // The customer is not sent to a page that has no way to pay.
        $mail = Mail::sent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'payment_failed')->first();
        $this->assertStringNotContainsString('order page', $mail->render());
        $this->assertStringContainsString('reply to this email', $mail->render());
    }

    public function test_email_settings_are_saved_on_the_settings_page()
    {
        $this->actingAs($this->owner())->get(route('admin.settings.store.edit'))
            ->assertInertia(fn ($page) => $page->where('documents.emails.shipped', true)->where('documents.emails.payment_failed', true));

        $settings = app(DocumentSettings::class)->toArray();
        $this->put(route('admin.settings.documents.update'), [...$settings, 'emails' => ['shipped' => false, 'delivered' => true]])->assertSessionHasNoErrors();

        $this->get(route('admin.settings.store.edit'))
            ->assertInertia(fn ($page) => $page->where('documents.emails.shipped', false)->where('documents.emails.delivered', true)->where('documents.emails.returned', true));
    }
}
