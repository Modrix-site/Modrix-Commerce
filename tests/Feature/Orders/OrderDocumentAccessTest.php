<?php

namespace Tests\Feature\Orders;

use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class OrderDocumentAccessTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
        $this->travelTo('2026-10-20 10:00:00');
    }

    private function placeOrder(?User $customer = null): Order
    {
        if ($customer !== null) {
            $this->actingAs($customer);
        }

        $this->addToCart($this->sellable('25.00', 5), 1);
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
        $this->flushSession();
        auth()->logout();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function pay(Order $order): Payment
    {
        $this->actingAs($this->userWithRole('order-manager'))->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        auth()->logout();

        return $order->payments()->firstOrFail();
    }

    private function assertPdf(TestResponse $response, string $filename): void
    {
        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment; filename='.$filename, (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_staff_with_orders_view_download_all_documents()
    {
        $order = $this->placeOrder();
        $payment = $this->pay($order);
        $this->actingAs($this->userWithRole('support-agent'));

        $this->assertPdf($this->get(route('admin.orders.documents.invoice', $order)), 'invoice-INV-2026-00001.pdf');
        $this->assertPdf($this->get(route('admin.orders.documents.receipt', [$order, $payment])), "receipt-{$order->number}-{$payment->id}.pdf");
        $this->assertPdf($this->get(route('admin.orders.documents.packing-slip', $order)), "packing-slip-{$order->number}.pdf");
    }

    public function test_staff_without_orders_view_get_403()
    {
        $order = $this->placeOrder();
        $this->pay($order);

        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.orders.documents.invoice', $order))->assertForbidden();
        $this->get(route('admin.orders.documents.packing-slip', $order))->assertForbidden();
    }

    public function test_invoice_404_before_issue()
    {
        $order = $this->placeOrder();

        $this->actingAs($this->owner())->get(route('admin.orders.documents.invoice', $order))->assertNotFound();
    }

    public function test_receipt_for_payment_of_another_order_404()
    {
        $customer = User::factory()->create();
        $mine = $this->placeOrder($customer);
        $this->pay($mine);
        $other = $this->placeOrder($customer);
        $otherPayment = $this->pay($other);

        $this->actingAs($this->owner())->get(route('admin.orders.documents.receipt', [$mine, $otherPayment]))->assertNotFound();
        $this->actingAs($customer)->get(route('account.orders.receipt', [$mine, $otherPayment]))->assertNotFound();
    }

    public function test_receipt_404_for_unpaid_payment()
    {
        $order = $this->placeOrder();
        $payment = $this->pay($order);
        Payment::query()->whereKey($payment->id)->update(['status' => Payment::STATUS_FAILED]);

        $this->actingAs($this->owner())->get(route('admin.orders.documents.receipt', [$order, $payment]))->assertNotFound();
    }

    public function test_customer_downloads_own_invoice_and_receipt()
    {
        $customer = User::factory()->create();
        $order = $this->placeOrder($customer);
        $payment = $this->pay($order);

        $this->actingAs($customer);
        $this->assertPdf($this->get(route('account.orders.invoice', $order)), 'invoice-INV-2026-00001.pdf');
        $this->assertPdf($this->get(route('account.orders.receipt', [$order, $payment])), "receipt-{$order->number}-{$payment->id}.pdf");
    }

    public function test_customer_cannot_download_other_customers_documents()
    {
        $order = $this->placeOrder(User::factory()->create());
        $payment = $this->pay($order);
        $guestOrder = $this->placeOrder();
        $this->pay($guestOrder);

        $this->actingAs(User::factory()->create());
        $this->get(route('account.orders.invoice', $order))->assertNotFound();
        $this->get(route('account.orders.receipt', [$order, $payment]))->assertNotFound();
        $this->get(route('account.orders.invoice', $guestOrder))->assertNotFound();
    }

    public function test_guest_is_redirected_to_login()
    {
        $order = $this->placeOrder(User::factory()->create());
        $this->pay($order);

        $this->get(route('account.orders.invoice', $order))->assertRedirect(route('login'));
    }

    public function test_customers_have_no_packing_slip_route()
    {
        $this->assertFalse(Route::has('account.orders.packing-slip'));
    }

    public function test_payment_email_has_invoice_attachment()
    {
        $order = $this->placeOrder();
        $this->pay($order);

        Mail::assertSent(OrderMail::class, function (OrderMail $mail) {
            if ($mail->kind !== 'paid') {
                return false;
            }

            $attachments = $mail->attachments();

            return count($attachments) === 1 && $attachments[0]->as === 'invoice-INV-2026-00001.pdf';
        });
        Mail::assertSent(OrderMail::class, fn (OrderMail $mail) => $mail->kind === 'placed' && $mail->attachments() === []);
    }
}
