<?php

namespace Tests\Feature\Notifications;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\MovementReason;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class NotificationTriggersTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        $this->staff = $this->userWithRole('store-manager');
    }

    /**
     * @return list<string>
     */
    private function kinds(User $user): array
    {
        return $user->notifications()->get()->sortBy('created_at')->map(fn ($n) => $n->data['kind'])->values()->all();
    }

    private function buy(?User $customer = null, string $payment = 'bank_transfer', ?ProductVariant $variant = null): Order
    {
        if ($customer !== null) {
            $this->actingAs($customer);
        }

        $this->addToCart($variant ?? $this->sellable('20.00', 5));
        $payload = $this->checkoutPayload(['payment_method' => $payment]);
        if ($variant?->product->is_digital) {
            unset($payload['shipping_method']);
        }
        $this->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();
        auth()->logout();

        return Order::query()->latest('id')->firstOrFail();
    }

    public function test_order_flow_notifies_staff()
    {
        $order = $this->buy();
        $workflow = app(OrderWorkflow::class);
        $workflow->recordManualPayment($order, $this->owner());
        $workflow->refund($order->fresh(), $this->owner(), Money::of('5.00', 'USD'), 'Damaged');

        $this->assertEqualsCanonicalizing(['order.placed', 'order.paid', 'order.refunded'], $this->kinds($this->staff));
        $first = $this->staff->notifications()->get()->firstWhere('data.kind', 'order.placed');
        $this->assertSame("/admin/orders/{$order->number}", $first->data['url']);
        $this->assertStringContainsString($order->number, $first->data['title']);
    }

    public function test_low_stock_and_new_customer()
    {
        $variant = $this->sellable('20.00', 10);
        $variant->forceFill(['low_stock_threshold' => 5])->save();
        app(InventoryService::class)->setOnHand($variant, 2, MovementReason::Adjustment);

        $this->post(route('register.store'), [
            'name' => 'Ana Cruz', 'email' => 'ana@example.com', 'password' => 'Str0ng!Passw0rd#', 'password_confirmation' => 'Str0ng!Passw0rd#',
        ]);

        $kinds = $this->kinds($this->staff);
        $this->assertContains('stock.low', $kinds);
        $low = $this->staff->notifications()->get()->firstWhere('data.kind', 'stock.low');
        $this->assertSame("/admin/products/{$variant->product_id}/edit", $low->data['url']);
        $this->assertContains('customer.new', $kinds);
    }

    public function test_customer_gets_order_updates()
    {
        $customer = User::factory()->create();
        $order = $this->buy($customer, 'cash_on_delivery');
        $workflow = app(OrderWorkflow::class);

        $workflow->ship($order->fresh(), $this->owner(), 'DHL', 'TRACK123', null);
        $workflow->markDelivered($order->fresh(), $this->owner());

        $this->assertEqualsCanonicalizing(['customer.order_confirmed', 'customer.order_shipped', 'customer.delivered'], $this->kinds($customer));
        $shipped = $customer->notifications()->get()->firstWhere('data.kind', 'customer.order_shipped');
        $this->assertSame('TRACK123', $shipped->data['params']['tracking']);
        $this->assertSame("/account/orders/{$order->number}", $shipped->data['url']);
        // Staff kinds never reach customers.
        $this->assertNotContains('order.placed', $this->kinds($customer));
    }

    public function test_guest_orders_give_no_customer_notifications()
    {
        $order = $this->buy(null, 'cash_on_delivery');
        app(OrderWorkflow::class)->ship($order->fresh(), $this->owner(), 'DHL', '1', null);

        $this->assertSame(0, DatabaseNotification::query()
            ->where('data->kind', 'like', 'customer.%')->count());
    }

    public function test_downloads_ready_only_with_links()
    {
        Storage::fake('local');
        $customer = User::factory()->create();
        $digital = $this->sellable('9.00', null, ['track_inventory' => false], ['is_digital' => true]);
        Storage::disk('local')->put("downloads/{$digital->product_id}/a.pdf", 'x');
        $digital->product->files()->create(['name' => 'A.pdf', 'disk' => 'local', 'path' => "downloads/{$digital->product_id}/a.pdf", 'size' => 1, 'position' => 0]);

        $withFiles = $this->buy($customer, 'bank_transfer', $digital);
        app(OrderWorkflow::class)->recordManualPayment($withFiles, $this->owner());
        $this->assertContains('customer.downloads_ready', $this->kinds($customer));

        $other = User::factory()->create();
        $physical = $this->buy($other);
        app(OrderWorkflow::class)->recordManualPayment($physical, $this->owner());
        $this->assertNotContains('customer.downloads_ready', $this->kinds($other));
    }
}
