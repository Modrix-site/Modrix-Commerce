<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Domains\Cart\CartManager;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\CartCheckedOut;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use EcoModular\CartAbandonmentRecovery\Mail\CartRecoveryMailable;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCart;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCartUnsubscribe;
use EcoModular\CartAbandonmentRecovery\Services\CartRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class CartAbandonmentRecoveryTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/cart-abandonment-recovery';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules'), $this->modulesPath);
        $modules = app(ModuleManager::class);
        $modules->install(self::MODULE);
        $modules->enable(self::MODULE, true);
        $this->enableCheckout();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_module_is_installed_and_enabled(): void
    {
        $this->assertDatabaseHas('extensions', [
            'name' => self::MODULE,
            'state' => 'enabled',
        ]);
    }

    public function test_detects_abandoned_cart_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'shopper@example.com',
            'name' => 'Jane Shopper',
        ]);

        $variant = $this->sellable('50.00');
        $cart = app(CartManager::class)->current(session()->driver(), $user, create: true);
        app(CartManager::class)->add($cart, $variant, 2);

        // Advance cart updated_at past abandonment cutoff (60 mins)
        $cart->forceFill(['updated_at' => now()->subHours(2)])->save();

        $service = app(CartRecoveryService::class);
        $detected = $service->detectAndSyncAbandonedCarts();

        $this->assertGreaterThanOrEqual(1, $detected);

        $abandoned = AbandonedCart::query()->where('cart_id', $cart->id)->first();
        $this->assertNotNull($abandoned);
        $this->assertSame('shopper@example.com', $abandoned->email);
        $this->assertSame('Jane Shopper', $abandoned->customer_name);
        $this->assertSame(AbandonedCart::STATUS_ABANDONED, $abandoned->status);
        $this->assertNotEmpty($abandoned->recovery_token);
        $this->assertSame(1, $abandoned->items_count);
    }

    public function test_captures_guest_email_and_creates_recovery_record(): void
    {
        $variant = $this->sellable('35.00');
        $cart = app(CartManager::class)->current(session()->driver(), null, create: true);
        app(CartManager::class)->add($cart, $variant, 1);

        $response = $this->postJson(route('modrixcommerce.cart-abandonment-recovery.capture-email'), [
            'email' => 'guest@example.com',
            'name' => 'John Guest',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $abandoned = AbandonedCart::query()->where('email', 'guest@example.com')->first();
        $this->assertNotNull($abandoned);
        $this->assertSame('John Guest', $abandoned->customer_name);
    }

    public function test_sends_scheduled_recovery_email(): void
    {
        Mail::fake();

        $abandoned = AbandonedCart::query()->create([
            'email' => 'customer@example.com',
            'customer_name' => 'Valued Customer',
            'recovery_token' => 'test-token-12345',
            'currency' => 'AED',
            'subtotal' => '150.0000',
            'items_count' => 1,
            'items_snapshot' => [
                [
                    'product_name' => 'Sample Item',
                    'quantity' => 1,
                    'unit_price' => '150.0000',
                    'formatted_price' => 'AED 150.00',
                ],
            ],
            'status' => AbandonedCart::STATUS_ABANDONED,
            'abandoned_at' => now()->subHours(2),
            'emails_sent_count' => 0,
            'next_email_scheduled_at' => now()->subMinute(),
        ]);

        $service = app(CartRecoveryService::class);
        $sentCount = $service->processScheduledEmails();

        $this->assertSame(1, $sentCount);

        Mail::assertSent(CartRecoveryMailable::class, function ($mail) {
            return $mail->hasTo('customer@example.com');
        });

        $abandoned->refresh();
        $this->assertSame(1, $abandoned->emails_sent_count);
        $this->assertNotNull($abandoned->last_email_sent_at);

        $this->assertDatabaseHas('abandoned_cart_emails', [
            'abandoned_cart_id' => $abandoned->id,
            'step' => 1,
            'recipient_email' => 'customer@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_one_click_recovery_restores_cart(): void
    {
        $variant = $this->sellable('75.00');

        $abandoned = AbandonedCart::query()->create([
            'email' => 'recoverme@example.com',
            'recovery_token' => 'restore-test-token',
            'currency' => 'AED',
            'subtotal' => '75.0000',
            'items_count' => 1,
            'items_snapshot' => [
                [
                    'variant_id' => $variant->id,
                    'product_name' => 'Recoverable Item',
                    'quantity' => 2,
                    'unit_price' => '75.0000',
                    'formatted_price' => 'AED 75.00',
                ],
            ],
            'status' => AbandonedCart::STATUS_ABANDONED,
            'abandoned_at' => now()->subDay(),
        ]);

        $response = $this->get(route('modrixcommerce.cart-abandonment-recovery.recover', ['token' => 'restore-test-token']));

        $response->assertRedirect(route('checkout.show'));

        $abandoned->refresh();
        $this->assertNotNull($abandoned->clicked_at);
        $this->assertSame(AbandonedCart::STATUS_IN_RECOVERY, $abandoned->status);

        $cart = app(CartManager::class)->current(session()->driver(), null);
        $this->assertNotNull($cart);
        $this->assertTrue($cart->items()->where('variant_id', $variant->id)->exists());
    }

    public function test_order_placement_converts_abandoned_cart(): void
    {
        $variant = $this->sellable('60.00');
        $cart = app(CartManager::class)->current(session()->driver(), null, create: true);
        app(CartManager::class)->add($cart, $variant, 1);

        $abandoned = AbandonedCart::query()->create([
            'cart_id' => $cart->id,
            'email' => 'buyer@example.com',
            'recovery_token' => 'conversion-token',
            'currency' => 'AED',
            'subtotal' => '60.0000',
            'items_count' => 1,
            'items_snapshot' => [],
            'status' => AbandonedCart::STATUS_ABANDONED,
            'abandoned_at' => now()->subHours(3),
            'next_email_scheduled_at' => now()->addHour(),
        ]);

        $order = Order::query()->forceCreate([
            'number' => 'ORD-1001',
            'email' => 'buyer@example.com',
            'currency' => 'AED',
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal' => '60.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '60.0000',
            'paid_total' => '60.0000',
            'payment_method_code' => 'bank_transfer',
            'payment_method_name' => 'Bank transfer',
            'placed_at' => now(),
            'paid_at' => now(),
        ]);

        CartCheckedOut::dispatch($cart->id, $order->id);

        $abandoned->refresh();
        $this->assertSame(AbandonedCart::STATUS_RECOVERED, $abandoned->status);
        $this->assertNotNull($abandoned->recovered_at);
        $this->assertSame($order->id, $abandoned->recovered_order_id);
        $this->assertNull($abandoned->next_email_scheduled_at);
    }

    public function test_unsubscribe_prevents_future_emails(): void
    {
        $abandoned = AbandonedCart::query()->create([
            'email' => 'optout@example.com',
            'recovery_token' => 'optout-token',
            'currency' => 'AED',
            'subtotal' => '50.0000',
            'items_count' => 1,
            'items_snapshot' => [],
            'status' => AbandonedCart::STATUS_ABANDONED,
            'abandoned_at' => now()->subHours(2),
            'next_email_scheduled_at' => now()->subMinute(),
        ]);

        $response = $this->get(route('modrixcommerce.cart-abandonment-recovery.unsubscribe', ['token' => 'optout-token']));

        $response->assertRedirect(route('home'));

        $this->assertTrue(AbandonedCartUnsubscribe::isUnsubscribed('optout@example.com'));

        $abandoned->refresh();
        $this->assertSame(AbandonedCart::STATUS_UNSUBSCRIBED, $abandoned->status);
        $this->assertNull($abandoned->next_email_scheduled_at);
    }

    public function test_admin_dashboard_loads_with_permission(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)->get(route('admin.modrixcommerce.cart-abandonment-recovery.index'));

        $response->assertOk();
    }

    public function test_admin_scanner_runs_via_action(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)->post(route('admin.modrixcommerce.cart-abandonment-recovery.scan'));

        $response->assertRedirect();
        $toast = session('inertia.flash_data.toast') ?? session('toast') ?? session('flash.toast');
        $this->assertNotNull($toast);
        $this->assertSame('success', $toast['type']);
    }

    public function test_admin_export_csv(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)->get(route('admin.modrixcommerce.cart-abandonment-recovery.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
