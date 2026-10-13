<?php

namespace Tests\Feature\Orders;

use App\Domains\Orders\Enums\DisplayStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderDisplayStatus;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Payments\Gateway\PaymentUpdate;
use App\Domains\Payments\Gateway\PaymentUpdateKind;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class PartialPaymentTest extends TestCase
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

    /** A $50 order (2 × $25, free shipping in fixtures may vary: use grand_total). */
    private function order(string $method = 'bank_transfer'): Order
    {
        $this->addToCart($this->sellable('25.00', 5), 2);
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => $method]))->assertSessionHasNoErrors();
        $this->flushSession();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function pay(Order $order, string $amount, array $extra = [])
    {
        return $this->actingAs($this->manager)->post(route('admin.orders.payments.store', $order), ['amount' => $amount, 'method' => 'cash', ...$extra]);
    }

    public function test_partial_payments_until_fully_paid()
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order();
        $total = $order->money('grand_total');

        $this->pay($order, '10.00', ['reference' => 'CASH-1'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(PaymentStatus::PartiallyPaid, $order->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(DisplayStatus::PartiallyPaid->value, $order->display_status);
        $this->assertSame($total->minus(Money::of('10.00', $order->currency))->toDecimal(), $order->balanceDue()->toDecimal());
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'method_code' => 'cash', 'provider' => 'manual', 'amount' => '10.0000', 'provider_reference' => 'CASH-1']);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'is_customer_visible' => true, 'message' => "Partial payment of {$this->fmt('10.00', $order)} recorded (Cash). Balance due {$order->balanceDue()->format()}."]);
        Event::assertNotDispatched(OrderPaid::class);
        $this->assertNotContains('ship', app(OrderWorkflow::class)->allowedActions($order));
        $this->assertNotContains('cancel', app(OrderWorkflow::class)->allowedActions($order));

        // Paying the rest (amount omitted = the balance) completes it.
        $this->actingAs($this->manager)->post(route('admin.orders.payments.store', $order), ['method' => 'bank_transfer'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertTrue($order->balanceDue()->isZero());
        $this->assertSame($order->grand_total, $order->paid_total);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    private function fmt(string $amount, Order $order): string
    {
        return Money::of($amount, $order->currency)->format();
    }

    public function test_invalid_payments_are_refused()
    {
        $order = $this->order();
        $over = $order->money('grand_total')->plus(Money::of('0.01', $order->currency))->toDisplayDecimal();

        $this->pay($order, $over)->assertSessionHasErrors('amount');
        $this->pay($order, '0')->assertSessionHasErrors('amount');
        $this->pay($order, '10', ['method' => 'bitcoin'])->assertSessionHasErrors('method');
        $this->pay($order, '10', ['paid_at' => now()->addDay()->toDateString()])->assertSessionHasErrors('paid_at');
        $this->assertSame(0, Payment::query()->where('order_id', $order->id)->count());

        // The last cent can be paid.
        $almost = $order->money('grand_total')->minus(Money::of('0.01', $order->currency))->toDisplayDecimal();
        $this->pay($order, $almost)->assertSessionHasNoErrors();
        $this->pay($order, '0.01')->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()?->payment_status);
    }

    public function test_amounts_respect_the_currency_precision()
    {
        $order = $this->order();
        DB::table('orders')->where('id', $order->id)->update(['currency' => 'JPY', 'grand_total' => '5000']);

        $this->pay($order->fresh(), '0.5')->assertSessionHasErrors('amount');
        $this->pay($order->fresh(), '100')->assertSessionHasNoErrors();
    }

    public function test_payment_date_is_recorded()
    {
        $order = $this->order();

        $this->pay($order, '5', ['paid_at' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01', Payment::query()->where('order_id', $order->id)->firstOrFail()->paid_at?->toDateString());
    }

    public function test_online_orders_can_be_settled_manually_and_open_sessions_close()
    {
        $order = $this->order();
        DB::table('orders')->where('id', $order->id)->update(['payment_method_code' => 'stripe', 'payment_method_name' => 'Card']);
        $session = new Payment;
        $session->forceFill(['order_id' => $order->id, 'method_code' => 'stripe', 'provider' => 'stripe', 'provider_reference' => 'cs_1', 'amount' => $order->grand_total, 'currency' => $order->currency, 'status' => Payment::STATUS_PENDING])->save();

        $this->pay($order->fresh(), '10')->assertSessionHasNoErrors();
        $this->assertSame(Payment::STATUS_EXPIRED, $session->fresh()?->status);

        // If the customer still completes that session, the extra money is flagged for refund.
        app(OrderWorkflow::class)->confirmGatewayPayment($session->fresh(), new PaymentUpdate(PaymentUpdateKind::Succeeded, 'cs_1', 'pi_1', $session->money()));
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'message' => 'A second payment was received for this order. Refund the extra payment to the customer.']);
    }

    public function test_pay_on_delivery_orders_can_ship_while_partly_paid()
    {
        $order = $this->order('cash_on_delivery');
        $this->pay($order, '5')->assertSessionHasNoErrors();

        $this->assertContains('ship', app(OrderWorkflow::class)->allowedActions($order->fresh()));
        $this->assertSame(DisplayStatus::PartiallyPaid, OrderDisplayStatus::forCustomer($order->fresh()));
    }

    public function test_the_order_page_shows_the_payments_panel()
    {
        $order = $this->order();
        $this->pay($order, '10', ['reference' => 'TILL-7', 'note' => 'Paid at the counter'])->assertSessionHasNoErrors();
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        $this->get(route('admin.orders.show', $order))->assertInertia(fn ($page) => $page
            ->where('order.balance_due.amount', $order->fresh()->balanceDue()->toDisplayDecimal())
            ->where('order.payments.0.method_label', 'Cash')
            ->where('order.payments.0.recorded_by', $this->manager->name)
            ->where('order.payments.0.reference', 'TILL-7')
            ->where('order.payments.0.note', 'Paid at the counter')
            ->where('order.payments.0.receipt_url', route('admin.orders.documents.receipt', [$order, $payment]))
            ->where('manualMethods', fn ($m) => collect($m)->pluck('value')->all() === ['cash', 'bank_transfer', 'card_terminal', 'other']));
    }

    public function test_recording_payments_needs_orders_manage()
    {
        $order = $this->order();

        $this->actingAs($this->userWithRole('support-agent'))->post(route('admin.orders.payments.store', $order), ['amount' => '1', 'method' => 'cash'])->assertForbidden();
    }
}
