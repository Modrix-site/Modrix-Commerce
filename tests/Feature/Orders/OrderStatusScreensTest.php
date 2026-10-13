<?php

namespace Tests\Feature\Orders;

use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class OrderStatusScreensTest extends TestCase
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

    private function order(?User $customer = null, bool $pay = true): Order
    {
        if ($customer) {
            $this->actingAs($customer);
        }
        $this->addToCart($this->sellable('25.00', 5));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasNoErrors();
        $this->flushSession();
        auth()->logout();
        $order = Order::query()->latest('id')->firstOrFail();
        if ($pay) {
            $this->actingAs($this->manager)->post(route('admin.orders.payments.store', $order))->assertSessionHasNoErrors();
        }

        return $order->refresh();
    }

    public function test_order_page_shows_the_headline_and_the_full_path_with_skipped_steps()
    {
        $order = $this->order();
        $this->actingAs($this->manager)->post(route('admin.orders.fulfillment', $order), ['status' => 'packing'])->assertSessionHasNoErrors();

        $this->get(route('admin.orders.show', $order))->assertInertia(fn (Assert $page) => $page
            ->where('order.display_status', 'packing')
            ->where('order.display_label', 'Packing')
            ->where('order.steps', fn ($steps) => collect($steps)->pluck('key')->all() === ['placed', 'paid', 'confirmed', 'picking', 'packing', 'packed', 'ready_for_dispatch', 'shipped', 'in_transit', 'out_for_delivery', 'delivered'])
            ->where('order.steps.3.done', true)
            ->where('order.steps.3.skipped', true)
            ->where('order.steps.4.done', true)
            ->where('order.steps.4.skipped', false)
            ->where('order.steps.5.done', false)
            ->where('emailDefaults.shipped', true)
            ->where('actions', fn ($actions) => collect($actions)->contains('advance:packed') && ! collect($actions)->contains('advance:picking')));
    }

    public function test_order_list_filters_by_headline_with_counts()
    {
        $packing = $this->order();
        $this->actingAs($this->manager)->post(route('admin.orders.fulfillment', $packing), ['status' => 'packing'])->assertSessionHasNoErrors();
        $this->order();
        $this->order(pay: false);

        $this->actingAs($this->manager)->get(route('admin.orders.index', ['display_status' => 'packing']))->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.number', $packing->number)
            ->where('orders.data.0.display_status', 'packing')
            ->where('statusCounts.packing', 1)
            ->where('statusCounts.confirmed', 1)
            ->where('statusCounts.pending_payment', 1)
            ->where('displayStatuses', fn ($options) => count($options) === 19));

        // "Ready to fulfil" (dashboard link): paid and not yet shipped.
        $this->get(route('admin.orders.index', ['ready' => 1]))->assertInertia(fn (Assert $page) => $page->has('orders.data', 2));
    }

    public function test_customers_see_their_headline_without_warehouse_steps()
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $this->actingAs($this->manager)->post(route('admin.orders.fulfillment', $order), ['status' => 'picking'])->assertSessionHasNoErrors();

        $this->actingAs($customer)->get(route('account.orders.show', $order))->assertInertia(fn (Assert $page) => $page
            ->where('order.status', 'processing')
            ->where('order.display_status', 'processing')
            ->where('order.fulfillment_status', 'unfulfilled'));

        $this->actingAs($this->manager)->post(route('admin.orders.fulfillment', $order), ['status' => 'in_transit'])->assertSessionHasErrors();
        $this->post(route('admin.orders.fulfillment', $order), ['status' => 'shipped'])->assertSessionHasNoErrors();
        $this->post(route('admin.orders.fulfillment', $order), ['status' => 'in_transit'])->assertSessionHasNoErrors();
        $this->actingAs($customer)->get(route('account.orders'))->assertInertia(fn (Assert $page) => $page->where('orders.data.0.status', 'in_transit'));
    }
}
