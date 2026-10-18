<?php

namespace Tests\Feature\Orders;

use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Pricing\Money;
use App\Domains\Reports\SalesReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class CustomerOrdersAndReportsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
    }

    public function test_customers_see_only_their_own_orders()
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);
        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $order = Order::query()->firstOrFail();

        $this->get(route('account.orders'))->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
        $this->get(route('account.orders.show', $order))->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('account.orders.show', $order))
            ->assertNotFound();
    }

    public function test_guest_orders_are_linked_only_after_email_verification()
    {
        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload(['email' => 'later@example.com']))->assertSessionHasNoErrors();
        $order = Order::query()->firstOrFail();

        $user = User::factory()->unverified()->create(['email' => 'later@example.com']);
        $this->assertNull($order->fresh()?->user_id);

        event(new Verified($user));
        $this->assertSame($user->id, $order->fresh()?->user_id);
    }

    public function test_sales_report_uses_store_timezone_and_excludes_cancelled_orders()
    {
        app(StoreSettings::class)->update([
            'store_name' => 'Test', 'default_currency' => 'USD', 'timezone' => 'Asia/Manila',
        ]);

        $this->addToCart($this->sellable('10.00', 10), 2);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $this->flushSession();
        $this->addToCart($this->sellable('40.00', 10));
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        [$first, $second] = Order::query()->orderBy('id')->get()->all();
        // 23:30 UTC on 1 March is 07:30 on 2 March in Manila.
        $first->forceFill(['placed_at' => '2026-03-01 23:30:00'])->save();
        $second->forceFill(['placed_at' => '2026-03-02 02:00:00'])->save();

        $this->actingAs($this->userWithRole('store-manager'))->post(route('admin.orders.cancel', $second), ['reason' => 'test']);

        $report = app(SalesReport::class)->build(
            CarbonImmutable::parse('2026-03-01', 'Asia/Manila'),
            CarbonImmutable::parse('2026-03-02', 'Asia/Manila'),
            'Asia/Manila',
            'USD',
        );

        $this->assertSame(1, $report['orders']);
        $this->assertSame('25.00', $report['gross_sales']->toDisplayDecimal()); // 20.00 + 5.00 shipping
        $this->assertSame(0, $report['series'][0]['orders']);
        $this->assertSame(1, $report['series'][1]['orders']);
        $this->assertSame(1, $report['orders_by_status']['cancelled']);
        $this->assertSame(2, $report['top_products'][0]['quantity']);
    }

    public function test_reports_page_and_dashboard_metrics()
    {
        $this->addToCart($this->sellable('15.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        $manager = $this->userWithRole('store-manager');
        $this->actingAs($manager)->get(route('admin.reports.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/reports/index')->where('report.orders', 1));

        $this->actingAs($manager)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sales.today.orders', 1)
                ->where('operations.pending_payment', 1)
                ->has('recentOrders', 1));

        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('sales', null)->where('recentOrders', null));
    }

    public function test_report_range_is_validated()
    {
        $this->actingAs($this->owner())
            ->get(route('admin.reports.index', ['from' => '2026-05-01', 'to' => '2026-04-01']))
            ->assertSessionHasErrors('from');
    }

    public function test_historical_order_currency_is_selected_instead_of_showing_zero_after_currency_change(): void
    {
        $this->withoutVite();
        $this->addToCart($this->sellable('15.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $manager = $this->userWithRole('store-manager');
        app(OrderWorkflow::class)->recordManualPayment($order, $manager);
        app(StoreSettings::class)->update(['store_name' => 'Test', 'default_currency' => 'AED', 'timezone' => 'UTC']);
        $this->actingAs($manager)->get(route('admin.reports.index'))->assertInertia(fn (Assert $page) => $page
            ->where('report.currency', 'USD')->where('storeCurrency', 'AED')->where('report.orders', 1)
            ->where('report.gross_sales.amount', '20.00')->where('report.payments_received.amount', '20.00')
            ->where('report.outstanding.amount', '0.00')->where('report.paid_orders', 1)->has('currencies', 2));
        $this->get(route('admin.reports.index', ['currency' => 'AED']))->assertInertia(fn (Assert $page) => $page->where('report.currency', 'AED')->where('report.orders', 0)->where('report.payments_received.amount', '0.00'));
        $aed = $order->replicate();
        $aed->forceFill(['number' => 'AED-TEST', 'idempotency_key' => null, 'currency' => 'AED', 'grand_total' => '100', 'paid_total' => '0', 'paid_at' => null, 'payment_status' => 'unpaid'])->save();
        $this->get(route('admin.reports.index', ['currency' => 'AED']))->assertInertia(fn (Assert $page) => $page->where('report.orders', 1)->where('report.gross_sales.amount', '100.00')->where('report.outstanding.amount', '100.00')->where('report.payments_received.amount', '0.00'));
        $this->get(route('admin.reports.index', ['currency' => 'USD']))->assertInertia(fn (Assert $page) => $page->where('report.orders', 1)->where('report.gross_sales.amount', '20.00')->where('report.payments_received.amount', '20.00'));
        $this->get(route('admin.reports.index', ['currency' => 'INVALID']))->assertSessionHasErrors('currency');
    }

    public function test_payments_are_bucketed_by_payment_date_and_only_completed_refunds_reduce_collections(): void
    {
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-03-03 12:00:00', 'UTC'));
        $this->addToCart($this->sellable('15.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $order->forceFill(['placed_at' => '2026-02-01 12:00:00'])->save();
        $manager = $this->userWithRole('store-manager');
        app(OrderWorkflow::class)->recordManualPayment($order, $manager, amount: Money::of('10', 'USD'), paidAt: CarbonImmutable::parse('2026-03-01 23:30:00', 'UTC'));
        foreach (['completed' => '2', 'pending' => '3', 'failed' => '4'] as $status => $amount) {
            $refund = new Refund;
            $refund->forceFill(['order_id' => $order->id, 'currency' => 'USD', 'amount' => $amount, 'reason' => 'Test', 'status' => $status, 'created_at' => '2026-03-02 01:00:00'])->save();
        }
        $report = app(SalesReport::class)->build(CarbonImmutable::parse('2026-03-02', 'Asia/Manila'), CarbonImmutable::parse('2026-03-02', 'Asia/Manila'), 'Asia/Manila', 'USD');
        $this->assertSame(0, $report['orders']);
        $this->assertSame('10.00', $report['payments_received']->toDisplayDecimal());
        $this->assertSame('10.00', $report['series'][0]['payments']->toDisplayDecimal());
        $this->assertSame('2.00', $report['refunds']->toDisplayDecimal());
        $this->assertSame('8.00', $report['net_collections']->toDisplayDecimal());
        $cohort = app(SalesReport::class)->build(CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-02-01'), 'UTC', 'USD');
        $this->assertSame('10.00', $cohort['outstanding']->toDisplayDecimal());
        $this->assertSame('0.00', $cohort['payments_received']->toDisplayDecimal());
    }
}
