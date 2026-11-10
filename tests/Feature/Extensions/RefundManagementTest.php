<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Orders\OrderRefunds;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Pricing\Money;
use App\Models\User;
use EcoModular\RefundManagement\Models\CreditMemo;
use EcoModular\RefundManagement\Models\RefundPaymentTrack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Smalot\PdfParser\Parser;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class RefundManagementTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpModules();
        File::copyDirectory(
            base_path('modules/modrixcommerce/refund-management'),
            $this->modulesPath.'/modrixcommerce/refund-management',
        );
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('modrixcommerce/refund-management');
        app(ModuleManager::class)->enable('modrixcommerce/refund-management', true);
        $this->enableCheckout();
        $this->variant = $this->sellable('25.00', 10);
        Mail::fake();
    }

    public function test_staff_process_partial_and_full_refunds_issue_credit_memos_and_track_payments(): void
    {
        $owner = $this->owner();
        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer, 2);
        $item = $order->items()->firstOrFail();

        // 1. Admin index lists the refundable order
        $this->actingAs($owner)
            ->get(route('admin.modrixcommerce.refund-management.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/refund-management/admin/index', false)
                ->where('refundableOrders.0.number', $order->number)
                ->where('stats.refundable_orders_count', 1));

        // 2. Process a partial item-based refund (1 of 2 units + restock 1)
        $this->actingAs($owner)
            ->post(route('admin.modrixcommerce.refund-management.refunds.store', $order), [
                'mode' => 'items',
                'reason' => 'Damaged sleeve on one unit',
                'items' => [
                    $item->id => ['quantity' => 1, 'restock' => 1],
                ],
                'shipping_refund' => '0.00',
                'adjustment_amount' => '0.00',
                'channel' => 'bank_transfer',
                'payout_status' => 'processing',
                'reference' => 'WIRE-1001',
                'destination' => $buyer->email,
                'notes' => 'Partial credit for 1 unit',
                'generate_credit_memo' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->payment_status);
        $this->assertSame(1, $item->fresh()->restocked_quantity);

        $partialRefund = Refund::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('25.0000', $partialRefund->amount);

        $memo = CreditMemo::query()->where('refund_id', $partialRefund->id)->firstOrFail();
        $this->assertStringStartsWith('CM-'.now()->year.'-00001', $memo->number);
        $this->assertSame('25.0000', $memo->grand_total);
        $this->assertSame('Partial credit for 1 unit', $memo->notes);

        $track = RefundPaymentTrack::query()->where('refund_id', $partialRefund->id)->firstOrFail();
        $this->assertSame('bank_transfer', $track->channel);
        $this->assertSame('processing', $track->payout_status);
        $this->assertSame('WIRE-1001', $track->reference);

        // 3. Update payment tracking to settled
        $this->actingAs($owner)
            ->post(route('admin.modrixcommerce.refund-management.payment-tracks.update', $partialRefund), [
                'channel' => 'bank_transfer',
                'payout_status' => 'settled',
                'reference' => 'WIRE-1001-CONFIRMED',
                'destination' => $buyer->email,
                'notes' => 'Confirmed by bank',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $track->refresh();
        $this->assertSame('settled', $track->payout_status);
        $this->assertSame('WIRE-1001-CONFIRMED', $track->reference);
        $this->assertNotNull($track->processed_at);

        // 4. Download the credit memo PDF
        $pdfResponse = $this->actingAs($owner)
            ->get(route('admin.modrixcommerce.refund-management.credit-memos.pdf', $memo))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());
        $pdfText = preg_replace('/\s+/u', ' ', (new Parser)->parseContent($pdfResponse->getContent())->getText());
        $this->assertStringContainsString($memo->number, $pdfText);
        $this->assertStringContainsString($order->number, $pdfText);
        $this->assertStringContainsString('Total credited', $pdfText);
        $this->assertStringContainsString($memo->lines[0]['name'], $pdfText);
        $this->assertStringContainsString(preg_replace('/\s+/u', ' ', $memo->totals['grand_total']), $pdfText);

        // 5. Process the remaining balance as a full refund
        $this->actingAs($owner)
            ->post(route('admin.modrixcommerce.refund-management.refunds.store', $order), [
                'mode' => 'full',
                'reason' => 'Customer returned remaining item',
                'restock_all' => '1',
                'channel' => 'bank_transfer',
                'payout_status' => 'settled',
                'generate_credit_memo' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame(2, $item->fresh()->restocked_quantity);
        $this->assertSame(2, CreditMemo::query()->where('order_id', $order->id)->count());
    }

    public function test_refunds_processed_via_core_order_workflow_automatically_receive_credit_memo_and_payment_track(): void
    {
        $owner = $this->owner();
        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer, 1);

        $refund = app(OrderRefunds::class)->refund(
            $order,
            $owner,
            Money::of('10.00', $order->currency),
            'Core order screen adjustment',
        );

        $memo = CreditMemo::query()->where('refund_id', $refund->id)->first();
        $track = RefundPaymentTrack::query()->where('refund_id', $refund->id)->first();

        $this->assertNotNull($memo);
        $this->assertNotNull($track);
        $this->assertSame('10.0000', $memo->grand_total);
        $this->assertSame('settled', $track->payout_status);
    }

    private function paidOrder(User $user, int $quantity): Order
    {
        $this->actingAs($user);
        $this->addToCart($this->variant, $quantity);
        $this->post(route('checkout.store'), $this->checkoutPayload(['email' => $user->email]))->assertRedirect();
        $order = Order::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
        app(OrderWorkflow::class)->recordManualPayment($order, $this->owner());

        return $order->refresh();
    }
}
