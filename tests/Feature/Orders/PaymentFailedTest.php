<?php

namespace Tests\Feature\Orders;

use App\Domains\Orders\Enums\DisplayStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Payments\Gateway\PaymentUpdate;
use App\Domains\Payments\Gateway\PaymentUpdateKind;
use App\Domains\Payments\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class PaymentFailedTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
    }

    private function orderWithAttempt(): array
    {
        $this->addToCart($this->sellable('25.00', 5));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
        $order = Order::query()->latest('id')->firstOrFail();

        $payment = new Payment;
        $payment->forceFill([
            'order_id' => $order->id,
            'method_code' => 'stripe',
            'provider' => 'stripe',
            'provider_reference' => 'cs_test_1',
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => Payment::STATUS_PENDING,
        ])->save();

        return [$order, $payment];
    }

    public function test_a_failed_attempt_marks_the_order_payment_failed()
    {
        [$order, $payment] = $this->orderWithAttempt();

        app(OrderWorkflow::class)->failGatewayPayment($payment, 'Card declined');

        $order->refresh();
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertSame(DisplayStatus::PaymentFailed->value, $order->display_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
    }

    public function test_a_later_successful_payment_clears_the_failure()
    {
        [$order, $payment] = $this->orderWithAttempt();
        $workflow = app(OrderWorkflow::class);
        $workflow->failGatewayPayment($payment, 'Card declined');

        $retry = $payment->replicate()->forceFill(['status' => Payment::STATUS_PENDING, 'provider_reference' => 'cs_test_2']);
        $retry->save();
        $workflow->confirmGatewayPayment($retry, new PaymentUpdate(PaymentUpdateKind::Succeeded, 'cs_test_2', 'pi_2', $retry->money()));

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(DisplayStatus::Confirmed->value, $order->display_status);
    }

    public function test_a_failed_order_can_still_be_paid_manually_and_cancelled()
    {
        [$order, $payment] = $this->orderWithAttempt();
        app(OrderWorkflow::class)->failGatewayPayment($payment, 'Card declined');

        $this->assertContains('record_payment', app(OrderWorkflow::class)->allowedActions($order->refresh()));
        $this->assertContains('cancel', app(OrderWorkflow::class)->allowedActions($order));

        $this->actingAs($this->owner())->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()?->payment_status);
    }
}
