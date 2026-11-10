<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Models\User;
use EcoModular\OrderReturns\Models\OrderReturnRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Customers request a return of items they received. Staff approve or reject
 * that request with a comment. The request does not refund or restock.
 */
class OrderReturnsTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpModules();
        File::copyDirectory(base_path('modules/modrixcommerce/order-returns'), $this->modulesPath.'/modrixcommerce/order-returns');
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('modrixcommerce/order-returns');
        app(ModuleManager::class)->enable('modrixcommerce/order-returns', true);
        $this->enableCheckout();
        $this->variant = $this->sellable('10.00', 5);
    }

    public function test_a_customer_requests_a_return_and_staff_approve_it_with_a_comment(): void
    {
        $buyer = User::factory()->create();
        $order = $this->deliveredOrder($buyer);
        $item = $order->items()->firstOrFail();

        $this->actingAs($buyer)->get(route('account.orders.show', $order))->assertInertia(fn (Assert $page) => $page
            ->where('returnSlots.0.props.canRequest', true)
            ->where('returnSlots.0.props.items.0.id', $item->id)
            ->where('returnSlots.0.props.items.0.returnable', 1));

        $this->actingAs($buyer)->post(route('modrixcommerce.order-returns.store', $order), [
            'comment' => 'The size is wrong',
            'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
        ])->assertSessionHasErrors('items');

        $this->actingAs($buyer)->post(route('modrixcommerce.order-returns.store', $order), [
            'comment' => 'The size is wrong',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertRedirect();

        $return = OrderReturnRequest::query()->firstOrFail();
        $this->assertSame('pending', $return->status);
        $this->assertSame('The size is wrong', $return->customer_comment);

        $this->actingAs($this->owner())->post(route('admin.modrixcommerce.order-returns.approve', $return), [])
            ->assertSessionHasErrors('comment');

        $this->actingAs($this->owner())->post(route('admin.modrixcommerce.order-returns.approve', $return), [
            'comment' => 'Approved, please send it back',
        ])->assertRedirect();

        $return->refresh();
        $this->assertSame('approved', $return->status);
        $this->assertSame('Approved, please send it back', $return->staff_comment);
        $this->assertSame($order->fresh()->fulfillment_status->value, 'delivered');
    }

    public function test_staff_reject_a_return_with_a_comment(): void
    {
        $buyer = User::factory()->create();
        $order = $this->deliveredOrder($buyer);
        $item = $order->items()->firstOrFail();

        $this->actingAs($buyer)->post(route('modrixcommerce.order-returns.store', $order), [
            'comment' => 'Changed my mind',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertRedirect();

        $return = OrderReturnRequest::query()->firstOrFail();

        $this->actingAs($this->owner())->post(route('admin.modrixcommerce.order-returns.reject', $return), [])
            ->assertSessionHasErrors('comment');

        $this->actingAs($this->owner())->post(route('admin.modrixcommerce.order-returns.reject', $return), [
            'comment' => 'Outside the condition we accept',
        ])->assertRedirect();

        $this->assertSame('rejected', $return->fresh()->status);
        $this->assertSame('Outside the condition we accept', $return->fresh()->staff_comment);
    }

    public function test_a_return_outside_the_configured_window_is_refused(): void
    {
        app(ModuleManager::class)->configure('modrixcommerce/order-returns', ['window_days' => '7', 'comment_min' => '5', 'eligible_after' => 'delivered']);
        $buyer = User::factory()->create();
        $order = $this->deliveredOrder($buyer);
        $order->forceFill(['delivered_at' => now()->subDays(10)])->save();
        $item = $order->items()->firstOrFail();

        $this->actingAs($buyer)->post(route('modrixcommerce.order-returns.store', $order), [
            'comment' => 'Too late but trying',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('order');

        $this->assertSame(0, OrderReturnRequest::query()->count());
    }

    public function test_the_account_menu_opens_the_customers_own_returns(): void
    {
        $buyer = User::factory()->create();
        $order = $this->deliveredOrder($buyer);
        $item = $order->items()->firstOrFail();

        $this->actingAs($buyer)->post(route('modrixcommerce.order-returns.store', $order), [
            'comment' => 'Changed my mind',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertRedirect();

        $this->actingAs($buyer)->get(route('account.show'))->assertInertia(fn (Assert $page) => $page
            ->where('storefront.accountLinks.0.title', 'Returns')
            ->where('storefront.accountLinks.0.href', route('modrixcommerce.order-returns.index', absolute: false)));

        $this->actingAs($buyer)->get(route('modrixcommerce.order-returns.index'))->assertInertia(fn (Assert $page) => $page
            ->component('module/modrixcommerce/order-returns/storefront/index', false)
            ->where('returns.0.status', 'pending')
            ->where('returns.0.order_number', $order->number)
            ->where('returns.0.items.0.name', $item->product_name));

        $this->actingAs(User::factory()->create())->get(route('modrixcommerce.order-returns.index'))->assertInertia(fn (Assert $page) => $page
            ->has('returns', 0));
    }

    private function deliveredOrder(User $user): Order
    {
        $this->actingAs($user);
        $this->addToCart($this->variant);
        $this->post(route('checkout.store'), $this->checkoutPayload(['email' => $user->email]))->assertRedirect();
        $order = Order::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
        $owner = $this->owner();
        $workflow = app(OrderWorkflow::class);
        $workflow->recordManualPayment($order, $owner);
        $workflow->ship($order->refresh(), $owner, 'Demo', 'TRACK1', null, false);
        $workflow->markDelivered($order->refresh(), $owner, false);

        return $order->refresh();
    }
}
