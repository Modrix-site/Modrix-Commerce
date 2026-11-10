<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\CartCheckedOut;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Orders\Models\OrderItem;
use EcoModular\SocialProofNotifications\Models\LiveProductViewer;
use EcoModular\SocialProofNotifications\Models\SocialProofEvent;
use EcoModular\SocialProofNotifications\Services\SocialProofService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class SocialProofNotificationsTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/social-proof-notifications';

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

    public function test_records_social_proof_event_on_order_checkout(): void
    {
        $variant = $this->sellable('75.00');
        $product = $variant->product;

        $order = Order::query()->forceCreate([
            'number' => 'ORD-PROOF-01',
            'email' => 'buyer@example.com',
            'currency' => 'AED',
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal' => '75.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '75.0000',
            'paid_total' => '75.0000',
            'payment_method_code' => 'manual',
            'payment_method_name' => 'Cash on Delivery',
            'placed_at' => now(),
        ]);

        OrderItem::query()->forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'product_name' => $product->localized('name'),
            'quantity' => 1,
            'unit_price' => '75.0000',
            'subtotal' => '75.0000',
            'discount_total' => '0.0000',
            'tax_total' => '0.0000',
            'total' => '75.0000',
        ]);

        OrderAddress::query()->forceCreate([
            'order_id' => $order->id,
            'type' => OrderAddress::SHIPPING,
            'first_name' => 'Michael',
            'last_name' => 'Scott',
            'line1' => '1725 Slough Ave',
            'city' => 'Scranton',
            'country_code' => 'US',
        ]);

        event(new CartCheckedOut(cartId: 999, orderId: $order->id));

        $this->assertDatabaseHas('social_proof_events', [
            'product_id' => $product->id,
            'event_type' => SocialProofEvent::TYPE_RECENT_PURCHASE,
            'customer_city' => 'Scranton',
            'customer_country' => 'US',
        ]);

        /** @var SocialProofEvent $event */
        $event = SocialProofEvent::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('Michael S.', $event->maskedCustomerName('first_name_initial'));
        $this->assertSame('Michael', $event->maskedCustomerName('first_name_only'));
        $this->assertSame('Someone', $event->maskedCustomerName('anonymous'));
    }

    public function test_storefront_feed_endpoint_returns_json_payload(): void
    {
        SocialProofEvent::query()->create([
            'event_type' => SocialProofEvent::TYPE_RECENT_PURCHASE,
            'product_name' => 'Wireless Gaming Mouse',
            'customer_name' => 'Dwight Schrute',
            'customer_city' => 'Honesdale',
            'customer_country' => 'US',
            'occurred_at' => now()->subMinutes(15),
            'is_active' => true,
        ]);

        $response = $this->getJson(route('modrixcommerce.social-proof-notifications.feed'));

        $response->assertOk();
        $response->assertJsonStructure([
            'enabled',
            'config',
            'feed' => [
                '*' => [
                    'id',
                    'type',
                    'headline',
                    'subtitle',
                    'productName',
                    'timeAgo',
                    'isVerified',
                ],
            ],
        ]);
        $this->assertCount(1, $response->json('feed'));
    }

    public function test_storefront_heartbeat_tracks_live_viewers(): void
    {
        $variant = $this->sellable('40.00');
        $product = $variant->product;

        $response = $this->postJson(route('modrixcommerce.social-proof-notifications.heartbeat'), [
            'product_id' => $product->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'viewers' => 1,
        ]);

        $this->assertSame(1, LiveProductViewer::countForProduct($product->id, 5));
    }

    public function test_storefront_tracks_impressions_and_clicks(): void
    {
        $event = SocialProofEvent::query()->create([
            'event_type' => SocialProofEvent::TYPE_RECENT_PURCHASE,
            'product_name' => 'Ergonomic Chair',
            'customer_name' => 'Jim Halpert',
            'customer_city' => 'Philly',
            'customer_country' => 'US',
            'occurred_at' => now()->subMinutes(30),
            'is_active' => true,
            'impressions_count' => 0,
            'clicks_count' => 0,
        ]);

        $impResponse = $this->postJson(route('modrixcommerce.social-proof-notifications.track.impression', ['event' => $event->id]));
        $impResponse->assertOk();

        $clickResponse = $this->postJson(route('modrixcommerce.social-proof-notifications.track.click', ['event' => $event->id]));
        $clickResponse->assertOk();

        $event->refresh();
        $this->assertSame(1, $event->impressions_count);
        $this->assertSame(1, $event->clicks_count);
    }

    public function test_product_badge_data_computes_counters(): void
    {
        $variant = $this->sellable('120.00');
        $product = $variant->product;

        SocialProofEvent::query()->create([
            'event_type' => SocialProofEvent::TYPE_RECENT_PURCHASE,
            'product_id' => $product->id,
            'product_name' => $product->localized('name'),
            'customer_name' => 'Pam Beesly',
            'occurred_at' => now()->subHours(5),
            'is_active' => true,
        ]);

        LiveProductViewer::recordHeartbeat($product->id, 'session-test-1');
        LiveProductViewer::recordHeartbeat($product->id, 'session-test-2');

        /** @var SocialProofService $service */
        $service = app(SocialProofService::class);
        $badge = $service->getProductBadgeData($product);

        $this->assertSame(1, $badge['recentOrdersCount']);
        $this->assertSame(2, $badge['liveViewersCount']);
    }

    public function test_admin_dashboard_loads_with_permission(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)->get(route('admin.modrixcommerce.social-proof-notifications.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_custom_alert(): void
    {
        $owner = $this->owner();
        $variant = $this->sellable('99.00');
        $product = $variant->product;

        $response = $this->actingAs($owner)->post(route('admin.modrixcommerce.social-proof-notifications.store'), [
            'event_type' => 'custom_alert',
            'product_id' => $product->id,
            'product_name' => $product->localized('name'),
            'customer_name' => 'Special Buyer',
            'customer_city' => 'Dubai',
            'customer_country' => 'AE',
            'message' => 'Flash special: 200 sold in the last 24h',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('social_proof_events', [
            'product_id' => $product->id,
            'event_type' => 'custom_alert',
            'customer_city' => 'Dubai',
        ]);
    }

    public function test_admin_can_toggle_and_delete_events(): void
    {
        $owner = $this->owner();

        $event = SocialProofEvent::query()->create([
            'event_type' => SocialProofEvent::TYPE_RECENT_PURCHASE,
            'product_name' => 'Desk Lamp',
            'customer_name' => 'Stanley Hudson',
            'occurred_at' => now(),
            'is_active' => true,
        ]);

        // Toggle active -> inactive
        $toggleResponse = $this->actingAs($owner)->post(route('admin.modrixcommerce.social-proof-notifications.toggle', ['event' => $event->id]));
        $toggleResponse->assertRedirect();
        $event->refresh();
        $this->assertFalse($event->is_active);

        // Delete
        $deleteResponse = $this->actingAs($owner)->delete(route('admin.modrixcommerce.social-proof-notifications.destroy', ['event' => $event->id]));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('social_proof_events', ['id' => $event->id]);
    }

    public function test_admin_can_seed_sample_events(): void
    {
        $owner = $this->owner();
        $this->sellable('50.00');

        $response = $this->actingAs($owner)->post(route('admin.modrixcommerce.social-proof-notifications.seed-sample'));

        $response->assertRedirect();
        $this->assertGreaterThan(0, SocialProofEvent::query()->count());
    }
}
