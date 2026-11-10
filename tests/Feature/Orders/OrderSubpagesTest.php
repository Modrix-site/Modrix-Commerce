<?php

namespace Tests\Feature\Orders;

use App\Domains\Cart\Models\Cart;
use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

/**
 * Orders → Invoices and Orders → Customer carts.
 */
class OrderSubpagesTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        config(['inertia.ssr.enabled' => false]);
    }

    private function placeOrder(string $email = 'buyer@example.com'): Order
    {
        $this->addToCart($this->sellable('20.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload(['email' => $email]))->assertSessionHasNoErrors();
        auth()->logout();
        $this->flushSession();

        return Order::query()->latest('id')->firstOrFail();
    }

    public function test_invoices_are_listed_newest_first_with_their_order_and_buyer()
    {
        $first = $this->placeOrder('first@example.com');
        $second = $this->placeOrder('second@example.com');
        $documents = app(OrderDocuments::class);
        $a = $documents->issueInvoice($first);
        $b = $documents->issueInvoice($second);

        $this->actingAs($this->owner())->get(route('admin.invoices.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/invoices/index')
                ->has('invoices.data', 2)
                ->where('invoices.data.0.number', $b->number)
                ->where('invoices.data.0.order_number', $second->number)
                ->where('invoices.data.0.buyer_email', 'second@example.com')
                ->where('invoices.data.0.total.amount', '25.00')
                ->where('invoices.data.1.number', $a->number));
    }

    public function test_invoices_can_be_searched_by_number_order_or_email()
    {
        $first = $this->placeOrder('first@example.com');
        $second = $this->placeOrder('second@example.com');
        app(OrderDocuments::class)->issueInvoice($first);
        app(OrderDocuments::class)->issueInvoice($second);

        $this->actingAs($this->owner())->get(route('admin.invoices.index', ['search' => 'first@']))
            ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1)->where('invoices.data.0.order_number', $first->number));
        $this->actingAs($this->owner())->get(route('admin.invoices.index', ['search' => $second->number]))
            ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1)->where('invoices.data.0.order_number', $second->number));
    }

    public function test_customer_carts_lists_carts_with_items_only()
    {
        $shopper = User::factory()->create(['email' => 'shopper@example.com']);
        $this->actingAs($shopper);
        $this->addToCart($this->sellable('10.00'), 2);
        $this->addToCart($this->sellable('5.00'));
        auth()->logout();
        $this->flushSession();
        // A guest cart and an empty cart.
        $this->addToCart($this->sellable('7.00'));
        $this->flushSession();
        Cart::query()->forceCreate(['token' => (string) str()->uuid(), 'currency' => 'USD']);

        $this->actingAs($this->owner())->get(route('admin.carts.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/carts/index')
                ->has('carts.data', 2)
                ->where('carts.data.0.customer', null)
                ->where('carts.data.0.items_count', 1)
                ->where('carts.data.1.customer.email', 'shopper@example.com')
                ->where('carts.data.1.items_count', 3)
                ->where('carts.data.1.subtotal.amount', '25.00'));

        $this->actingAs($this->owner())->get(route('admin.carts.index', ['search' => 'shopper']))
            ->assertInertia(fn (Assert $page) => $page->has('carts.data', 1));
    }

    public function test_both_pages_need_permission_to_view_orders()
    {
        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.invoices.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.carts.index'))->assertForbidden();
    }
}
