<?php

namespace Tests\Feature\Orders;

use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Payments\Gateway\PaymentUpdate;
use App\Domains\Payments\Gateway\PaymentUpdateKind;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Smalot\PdfParser\Parser;
use Tests\Support\CommerceFixtures;
use Tests\Support\StripeFixtures;
use Tests\TestCase;

/**
 * Money-handling edge cases found in review of partial payments.
 */
class PartialPaymentEdgeCasesTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, StripeFixtures;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
        $this->manager = $this->userWithRole('order-manager');
    }

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

    public function test_a_gateway_payment_after_a_partial_cash_payment_completes_the_order_and_flags_the_excess()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $session] = $this->placeStripeOrder('25.00', 2);
        Event::fake([OrderPaid::class]);

        $this->pay($order, '10')->assertSessionHasNoErrors();
        // The customer had already paid on the provider page.
        app(OrderWorkflow::class)->confirmGatewayPayment($session->fresh(), new PaymentUpdate(PaymentUpdateKind::Succeeded, $session->provider_reference, 'pi_1', $session->money()));

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        Event::assertDispatched(OrderPaid::class);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'message' => 'This order was overpaid by '.Money::of('10', $order->currency)->format().'. Refund the extra to the customer.']);
    }

    public function test_a_partly_paid_then_partly_refunded_order_can_still_collect_its_balance()
    {
        $order = $this->order();
        $this->pay($order, '10')->assertSessionHasNoErrors();
        $this->post(route('admin.orders.refunds.store', $order), ['amount' => '5', 'reason' => 'Goodwill'])->assertSessionHasNoErrors();

        $this->assertContains('record_payment', app(OrderWorkflow::class)->allowedActions($order->fresh()));
        $this->pay($order->fresh(), $order->fresh()->balanceDue()->toDisplayDecimal())->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->balanceDue()->isZero());
    }

    public function test_a_partly_paid_pay_on_delivery_order_is_not_completed_on_delivery()
    {
        $order = $this->order('cash_on_delivery');
        $this->pay($order, '5')->assertSessionHasNoErrors();
        $this->post(route('admin.orders.fulfillment', $order), ['status' => 'shipped'])->assertSessionHasNoErrors();
        $this->post(route('admin.orders.fulfillment', $order), ['status' => 'delivered'])->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Processing, $order->fresh()?->status);

        $this->pay($order->fresh(), $order->fresh()->balanceDue()->toDisplayDecimal())->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Completed, $order->fresh()?->status);
    }

    public function test_an_online_order_settled_in_cash_is_refunded_manually()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order] = $this->placeStripeOrder('25.00', 2);

        $this->pay($order, $order->money('grand_total')->toDisplayDecimal())->assertSessionHasNoErrors();
        $this->post(route('admin.orders.refunds.store', $order), ['amount' => '5', 'reason' => 'Damaged'])->assertSessionHasNoErrors();

        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->fresh()?->payment_status);
        $this->assertDatabaseHas('refunds', ['order_id' => $order->id, 'amount' => '5.0000', 'provider_reference' => null]);
    }

    public function test_today_is_accepted_in_stores_ahead_of_utc()
    {
        app(SettingsRepository::class)->set('store.timezone', 'Pacific/Auckland');
        // 20:00 UTC on the 12th is 09:00 on the 13th in Auckland.
        $this->travelTo(Carbon::parse('2026-10-12 20:00:00', 'UTC'));
        $order = $this->order();

        $this->pay($order, '5', ['paid_at' => '2026-10-13'])->assertSessionHasNoErrors();
        $this->pay($order, '5', ['paid_at' => '2026-10-14'])->assertSessionHasErrors('paid_at');
    }

    public function test_receipts_show_how_that_payment_was_made()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order] = $this->placeStripeOrder('25.00', 2);
        $this->pay($order, '10')->assertSessionHasNoErrors();

        $payment = Payment::query()->where('order_id', $order->id)->where('provider', 'manual')->firstOrFail();
        $text = (new Parser)->parseContent(app(OrderDocuments::class)->receiptPdf($payment))->getText();

        $this->assertStringContainsString('Cash', $text);
    }

    public function test_bulk_pdfs_are_limited_to_thirty_orders()
    {
        $order = $this->order();

        $this->actingAs($this->manager)->get(route('admin.orders.bulk.documents', ['type' => 'packing-slips', 'numbers' => array_fill(0, 31, $order->number)]))
            ->assertSessionHasErrors('numbers');
    }
}
