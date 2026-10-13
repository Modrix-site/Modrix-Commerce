<?php

namespace Tests\Feature\Orders;

use App\Domains\Orders\Documents\DocumentSettings;
use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Payments\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Smalot\PdfParser\Parser;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class OrderDocumentsPdfTest extends TestCase
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

    private function paidOrder(array $product = []): Order
    {
        $this->addToCart($this->sellable('25.00', 5, product: $product), 2);
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
        $this->flushSession();
        $order = Order::query()->latest('id')->firstOrFail();
        $this->actingAs($this->userWithRole('order-manager'))->post(route('admin.orders.payments.store', $order), ['reference' => 'BANK-REF-77'])->assertSessionHasNoErrors();
        auth()->logout();

        return $order->refresh();
    }

    private function text(string $pdf): string
    {
        $this->assertStringStartsWith('%PDF', $pdf);

        return preg_replace('/\s+/u', ' ', (new Parser)->parseContent($pdf)->getText()) ?? '';
    }

    public function test_invoice_pdf_contains_number_and_total()
    {
        $order = $this->paidOrder();
        $text = $this->text(app(OrderDocuments::class)->invoicePdf($order->invoice));

        $this->assertStringContainsString('INV-2026-00001', $text);
        $this->assertStringContainsString($order->money('grand_total')->format(), $text);
        $this->assertStringContainsString($order->number, $text);
    }

    public function test_invoice_pdf_uses_snapshot_after_rename()
    {
        $order = $this->paidOrder(['name' => 'Original Mug']);
        OrderItem::query()->where('order_id', $order->id)->update(['product_name' => 'Changed Mug']);
        app(DocumentSettings::class)->update(['footer' => 'Footer added later']);

        $text = $this->text(app(OrderDocuments::class)->invoicePdf($order->invoice));

        $this->assertStringContainsString('Original Mug', $text);
        $this->assertStringNotContainsString('Changed Mug', $text);
        $this->assertStringNotContainsString('Footer added later', $text);
    }

    public function test_receipt_shows_reference_and_balance()
    {
        $order = $this->paidOrder();
        $payment = $order->payments()->firstOrFail();

        $text = $this->text(app(OrderDocuments::class)->receiptPdf($payment));

        $this->assertStringContainsString('BANK-REF-77', $text);
        $this->assertStringContainsString($order->number, $text);
        $this->assertStringContainsString('Balance due', $text);
        $this->assertStringContainsString($order->money('grand_total')->minus($order->money('grand_total'))->format(), $text);
    }

    public function test_receipt_rejects_unpaid_payment()
    {
        $order = $this->paidOrder();
        $payment = $order->payments()->firstOrFail();
        Payment::query()->whereKey($payment->id)->update(['status' => Payment::STATUS_FAILED]);

        $this->expectException(InvalidArgumentException::class);
        app(OrderDocuments::class)->receiptPdf($payment->fresh());
    }

    public function test_packing_slip_has_items_without_prices()
    {
        $order = $this->paidOrder();
        $item = $order->items()->firstOrFail();

        $text = $this->text(app(OrderDocuments::class)->packingSlipPdf($order));

        $this->assertStringContainsString($item->sku, $text);
        $this->assertStringContainsString('Packing slip', $text);
        $this->assertStringNotContainsString($order->money('grand_total')->format(), $text);
        $this->assertStringNotContainsString('25.00', $text);
    }

    public function test_unicode_names_render()
    {
        $order = $this->paidOrder(['name' => 'Ünïcødé Café Ñandú']);

        $text = $this->text(app(OrderDocuments::class)->invoicePdf($order->invoice));

        $this->assertStringContainsString('Ünïcødé Café Ñandú', $text);
    }

    public function test_seller_details_are_escaped_and_never_fetch_remote_content()
    {
        Http::fake();
        app(DocumentSettings::class)->update([
            'seller_address' => '<img src="http://example.test/x.png"> 1 Market St',
            'tax_id' => 'VAT-123',
            'footer' => 'Thank you <b>friend</b>',
        ]);
        $order = $this->paidOrder();

        $text = $this->text(app(OrderDocuments::class)->invoicePdf($order->invoice));

        $this->assertStringContainsString('1 Market St', $text);
        $this->assertStringContainsString('VAT-123', $text);
        $this->assertStringContainsString('<b>friend</b>', $text);
        Http::assertNothingSent();
    }
}
