<?php

namespace Tests\Feature\Orders;

use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Models\Order;
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
}
