<?php

namespace Tests\Feature\Orders;

use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Smalot\PdfParser\Parser;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class BulkOrderActionsTest extends TestCase
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

    private function order(bool $pay = true, array $checkout = []): Order
    {
        $this->addToCart($this->sellable('25.00', 5));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer', ...$checkout]))->assertSessionHasNoErrors();
        $this->flushSession();
        $order = Order::query()->latest('id')->firstOrFail();
        if ($pay) {
            $this->actingAs($this->manager)->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        }

        return $order->refresh();
    }

    public function test_bulk_move_applies_only_legal_moves_and_reports_skips()
    {
        $a = $this->order();
        $b = $this->order();
        $unpaid = $this->order(pay: false);

        $this->actingAs($this->manager)
            ->post(route('admin.orders.bulk.fulfillment'), ['numbers' => [$a->number, $b->number, $unpaid->number, 'EM-999999'], 'status' => 'packed'])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', "Moved 2 orders to Packed. Skipped 2: {$unpaid->number} (this order cannot move to \"packed\" from its current state), EM-999999 (not found).");

        $this->assertSame(FulfillmentStatus::Packed, $a->fresh()?->fulfillment_status);
        $this->assertSame(FulfillmentStatus::Packed, $b->fresh()?->fulfillment_status);
        $this->assertSame(FulfillmentStatus::Unfulfilled, $unpaid->fresh()?->fulfillment_status);
    }

    public function test_bulk_ship_respects_the_email_choice()
    {
        $a = $this->order();

        $this->actingAs($this->manager)->post(route('admin.orders.bulk.fulfillment'), ['numbers' => [$a->number], 'status' => 'shipped', 'notify' => false])->assertRedirect();
        $this->assertSame(FulfillmentStatus::Shipped, $a->fresh()?->fulfillment_status);
        Mail::assertNotSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'shipped');
    }

    public function test_bulk_move_validates_and_needs_orders_manage()
    {
        $a = $this->order();

        $this->actingAs($this->manager)->post(route('admin.orders.bulk.fulfillment'), ['numbers' => [$a->number], 'status' => 'returned'])->assertSessionHasErrors('status');
        $this->post(route('admin.orders.bulk.fulfillment'), ['numbers' => array_fill(0, 101, $a->number), 'status' => 'packed'])->assertSessionHasErrors('numbers');
        $this->post(route('admin.orders.bulk.fulfillment'), ['numbers' => [], 'status' => 'packed'])->assertSessionHasErrors('numbers');

        $this->actingAs($this->userWithRole('support-agent'))->post(route('admin.orders.bulk.fulfillment'), ['numbers' => [$a->number], 'status' => 'packed'])->assertForbidden();
    }

    public function test_bulk_documents_combine_packing_slips_and_invoices()
    {
        $a = $this->order();
        $b = $this->order();
        $unpaid = $this->order(pay: false);

        $slips = $this->actingAs($this->userWithRole('support-agent'))
            ->get(route('admin.orders.bulk.documents', ['type' => 'packing-slips', 'numbers' => [$a->number, $b->number, $unpaid->number]]));
        $slips->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $text = (new Parser)->parseContent((string) $slips->getContent())->getText();
        foreach ([$a, $b, $unpaid] as $o) {
            $this->assertStringContainsString($o->number, $text);
        }

        // Orders without an invoice are skipped.
        $invoices = $this->get(route('admin.orders.bulk.documents', ['type' => 'invoices', 'numbers' => [$a->number, $b->number, $unpaid->number]]));
        $invoices->assertOk();
        $text = (new Parser)->parseContent((string) $invoices->getContent())->getText();
        $this->assertStringContainsString((string) $a->invoice?->number, $text);
        $this->assertStringContainsString((string) $b->invoice?->number, $text);
        $this->assertStringNotContainsString($unpaid->number, $text);

        $this->get(route('admin.orders.bulk.documents', ['type' => 'invoices', 'numbers' => [$unpaid->number]]))->assertNotFound();
        $this->get(route('admin.orders.bulk.documents', ['type' => 'nope', 'numbers' => [$a->number]]))->assertSessionHasErrors('type');

        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.orders.bulk.documents', ['type' => 'packing-slips', 'numbers' => [$a->number]]))->assertForbidden();
    }

    public function test_csv_export_of_selected_or_filtered_orders()
    {
        $a = $this->order(checkout: ['shipping' => ['first_name' => '=HYPERLINK("http://evil")']]);
        $b = $this->order(pay: false);

        $selected = $this->actingAs($this->userWithRole('support-agent'))->get(route('admin.orders.export', ['numbers' => [$a->number]]));
        $selected->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $rows = array_map('str_getcsv', array_filter(explode("\n", ltrim((string) $selected->streamedContent(), "\u{FEFF}"))));
        $this->assertSame(['number', 'placed_at', 'customer', 'email', 'phone', 'country', 'status', 'payment_status', 'fulfillment_status', 'currency', 'subtotal', 'discount', 'shipping', 'tax', 'total', 'paid', 'refunded', 'balance_due', 'payment_method', 'shipping_method', 'carrier', 'tracking_number', 'coupon'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame($a->number, $rows[1][0]);
        $this->assertStringStartsWith("'=HYPERLINK", $rows[1][2]);
        $this->assertSame('confirmed', $rows[1][6]);
        $this->assertSame('0.00', $rows[1][17]);

        // Without a selection, the current filters apply.
        $filtered = $this->get(route('admin.orders.export', ['display_status' => 'pending_payment']));
        $rows = array_map('str_getcsv', array_filter(explode("\n", ltrim((string) $filtered->streamedContent(), "\u{FEFF}"))));
        $this->assertCount(2, $rows);
        $this->assertSame($b->number, $rows[1][0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'orders.exported']);

        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.orders.export'))->assertForbidden();
    }
}
