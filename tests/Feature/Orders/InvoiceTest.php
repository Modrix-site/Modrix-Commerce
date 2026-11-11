<?php

namespace Tests\Feature\Orders;

use App\Domains\Orders\Documents\DocumentSettings;
use App\Domains\Orders\Documents\InvoiceNumbers;
use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Invoice;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class InvoiceTest extends TestCase
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

    private function placeOrder(): Order
    {
        $this->addToCart($this->sellable('25.00', 5), 2);
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
        $this->flushSession();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function pay(Order $order): Order
    {
        $this->actingAs($this->userWithRole('order-manager'))->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        auth()->logout();

        return $order->refresh();
    }

    public function test_paid_orders_get_sequential_invoices()
    {
        $first = $this->pay($this->placeOrder());
        $second = $this->pay($this->placeOrder());

        $this->assertSame('INV-2026-00001', $first->invoice?->number);
        $this->assertSame('INV-2026-00002', $second->invoice?->number);
        $this->assertSame($first->grand_total, $first->invoice->totals['grand_total']);
        $this->assertDatabaseHas('order_events', ['order_id' => $first->id, 'type' => 'invoice_issued']);
    }

    public function test_issuing_twice_returns_the_same_invoice()
    {
        $order = $this->placeOrder();
        $documents = app(OrderDocuments::class);

        $invoice = $documents->issueInvoice($order);
        $again = $documents->issueInvoice($order);

        $this->assertSame($invoice->id, $again->id);
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(2, app(InvoiceNumbers::class)->next());
    }

    public function test_snapshot_does_not_change_after_issue()
    {
        $order = $this->pay($this->placeOrder());
        $invoice = $order->invoice;
        $this->assertNotNull($invoice);
        $line = $invoice->lines[0]['name'];
        $address = $invoice->buyer['address']['line1'];

        $order->items()->firstOrFail()->product?->forceFill(['name' => 'Renamed product'])->save();
        OrderAddress::query()->where('order_id', $order->id)->update(['line1' => '1 New Street']);
        app(DocumentSettings::class)->update(['footer' => 'New footer']);

        $fresh = $invoice->fresh();
        $this->assertSame($line, $fresh?->lines[0]['name']);
        $this->assertSame($address, $fresh?->buyer['address']['line1']);
        $this->assertNull($fresh?->notes);
    }

    public function test_invoices_are_immutable()
    {
        $invoice = app(OrderDocuments::class)->issueInvoice($this->placeOrder());

        $this->expectException(LogicException::class);
        $invoice->forceFill(['number' => 'X'])->save();
    }

    public function test_counter_is_safe_across_transactions()
    {
        $numbers = app(InvoiceNumbers::class);

        $a = DB::transaction(fn () => $numbers->take());
        $b = DB::transaction(fn () => $numbers->take());

        $this->assertSame([1, 'INV-2026-00001'], [$a['sequence'], $a['number']]);
        $this->assertSame([2, 'INV-2026-00002'], [$b['sequence'], $b['number']]);
        $this->assertSame(3, $numbers->next());
    }

    public function test_next_number_can_only_move_up()
    {
        $settings = app(DocumentSettings::class);
        $settings->update(['next_invoice_number' => 100]);

        try {
            $settings->update(['next_invoice_number' => 50]);
            $this->fail('Lowering the next invoice number must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('next_invoice_number', $e->errors());
        }

        $this->assertSame('INV-2026-00100', app(OrderDocuments::class)->issueInvoice($this->placeOrder())->number);
    }

    public function test_custom_prefix_and_padding()
    {
        app(DocumentSettings::class)->update(['invoice_prefix' => 'FAC-', 'invoice_padding' => 3]);

        $this->assertSame('FAC-001', app(OrderDocuments::class)->issueInvoice($this->placeOrder())->number);
    }

    public function test_documents_settings_page_saves_and_validates()
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('admin.settings.documents.edit'))->assertRedirect('/admin/settings/store#documents');
        $this->get(route('admin.settings.store.edit'))
            ->assertInertia(fn ($page) => $page
                ->component('admin/settings/store')
                ->where('documents.settings.invoice_prefix', 'INV-{year}-')
                ->where('documents.settings.next_invoice_number', 1)
                ->where('documents.preview', 'INV-2026-00001'));

        $valid = [
            'invoice_prefix' => 'FAC/{year}/',
            'invoice_padding' => 4,
            'next_invoice_number' => 42,
            'tax_id' => 'ES-B12345678',
            'seller_address' => "1 Market St\nMadrid",
            'footer' => 'Payment within 30 days.',
            'logo_media_id' => null,
        ];

        $this->put(route('admin.settings.documents.update'), $valid)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('FAC/2026/0042', app(InvoiceNumbers::class)->format(app(InvoiceNumbers::class)->next()));
        $this->assertSame('ES-B12345678', app(DocumentSettings::class)->toArray()['tax_id']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.documents_updated']);

        $this->put(route('admin.settings.documents.update'), [...$valid, 'next_invoice_number' => 10])->assertSessionHasErrors('next_invoice_number');
        $this->put(route('admin.settings.documents.update'), [...$valid, 'invoice_prefix' => '<script>'])->assertSessionHasErrors('invoice_prefix');
        $this->put(route('admin.settings.documents.update'), [...$valid, 'invoice_padding' => 12])->assertSessionHasErrors('invoice_padding');
        $this->put(route('admin.settings.documents.update'), [...$valid, 'logo_media_id' => 999])->assertSessionHasErrors('logo_media_id');
        $this->assertSame(42, app(InvoiceNumbers::class)->next());

        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.settings.documents.edit'))->assertForbidden();
        $this->put(route('admin.settings.documents.update'), $valid)->assertForbidden();
    }

    public function test_payment_and_its_email_survive_an_invoice_failure()
    {
        $this->onlySqlite('a table is dropped to break numbering, and DDL is not rolled back on MySQL.');

        $order = $this->placeOrder();
        // Break invoice numbering: issuing now throws.
        Schema::drop('document_counters');

        $this->actingAs($this->userWithRole('order-manager'))
            ->post(route('admin.orders.payments.store', $order))
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('paid', $order->fresh()?->payment_status->value);
        $this->assertNull($order->fresh()?->invoice);
        Mail::assertSent(OrderMail::class, fn ($m) => $m->kind === 'paid');
    }

    public function test_saving_other_settings_from_a_stale_page_keeps_the_counter()
    {
        $settings = app(DocumentSettings::class);
        $loaded = $settings->toArray(); // next = 1 when the page was opened

        // Invoices are issued while the page is open.
        $this->pay($this->placeOrder());
        $this->pay($this->placeOrder());

        $this->actingAs($this->owner())->put(route('admin.settings.documents.update'), [
            ...$loaded,
            'loaded_next_invoice_number' => $loaded['next_invoice_number'],
            'footer' => 'Thanks!',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Thanks!', $settings->toArray()['footer']);
        $this->assertSame(3, app(InvoiceNumbers::class)->next());
    }

    public function test_staff_can_issue_invoice_for_unpaid_order()
    {
        $order = $this->placeOrder();

        $this->actingAs($this->userWithRole('support-agent'))->post(route('admin.orders.invoice.store', $order))->assertForbidden();
        $this->assertNull($order->fresh()?->invoice);

        $manager = $this->userWithRole('order-manager');
        $this->actingAs($manager)->post(route('admin.orders.invoice.store', $order))->assertSessionHasNoErrors();
        $this->assertSame($manager->id, $order->fresh()?->invoice?->issued_by);
    }
}
