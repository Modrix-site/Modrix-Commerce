<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Models\User;
use EcoModular\FraudDetection\Models\BlacklistedIp;
use EcoModular\FraudDetection\Models\FraudRule;
use EcoModular\FraudDetection\Models\FraudScore;
use EcoModular\FraudDetection\Services\FraudDashboardService;
use EcoModular\FraudDetection\Services\FraudReportService;
use EcoModular\FraudDetection\Services\RiskScoringEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class FraudDetectionTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/fraud-detection';

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

    private function createSampleOrder(array $overrides = []): Order
    {
        $order = new Order;
        $order->forceFill([
            'number' => $overrides['number'] ?? 'ORD-'.uniqid(),
            'email' => $overrides['email'] ?? $overrides['customer_email'] ?? 'customer@example.com',
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Unpaid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'currency' => $overrides['currency'] ?? 'USD',
            'grand_total' => $overrides['total'] ?? $overrides['grand_total'] ?? '100.0000',
            'subtotal' => $overrides['total'] ?? $overrides['subtotal'] ?? '100.0000',
            'payment_method_code' => $overrides['payment_method_code'] ?? 'credit_card',
            'payment_method_name' => $overrides['payment_method_name'] ?? 'Credit Card',
            'ip_address' => $overrides['ip_address'] ?? '192.168.1.1',
            'placed_at' => now(),
        ]);
        $order->save();

        // Create billing address
        $billingData = $overrides['billing_address'] ?? [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'line1' => '123 Main St',
            'city' => 'New York',
            'country_code' => 'US',
        ];
        $billing = new OrderAddress;
        $billing->forceFill([
            'order_id' => $order->id,
            'type' => OrderAddress::BILLING,
            'first_name' => $billingData['first_name'] ?? 'John',
            'last_name' => $billingData['last_name'] ?? 'Doe',
            'line1' => $billingData['line1'] ?? $billingData['address1'] ?? '123 Main St',
            'city' => $billingData['city'] ?? 'New York',
            'country_code' => $billingData['country_code'] ?? $billingData['country'] ?? 'US',
        ]);
        $billing->save();

        // Create shipping address
        $shippingData = $overrides['shipping_address'] ?? [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'line1' => '123 Main St',
            'city' => 'New York',
            'country_code' => 'US',
        ];
        $shipping = new OrderAddress;
        $shipping->forceFill([
            'order_id' => $order->id,
            'type' => OrderAddress::SHIPPING,
            'first_name' => $shippingData['first_name'] ?? 'John',
            'last_name' => $shippingData['last_name'] ?? 'Doe',
            'line1' => $shippingData['line1'] ?? $shippingData['address1'] ?? '123 Main St',
            'city' => $shippingData['city'] ?? 'New York',
            'country_code' => $shippingData['country_code'] ?? $shippingData['country'] ?? 'US',
        ]);
        $shipping->save();

        $order->setRelation('billingAddress', $billing);
        $order->setRelation('shippingAddress', $shipping);

        return $order;
    }

    public function test_module_is_installed_and_enabled(): void
    {
        $this->assertDatabaseHas('extensions', [
            'name' => self::MODULE,
            'state' => 'enabled',
        ]);
    }

    public function test_scoring_engine_assigns_low_score_for_legitimate_order(): void
    {
        $order = $this->createSampleOrder([
            'total' => '50.0000',
            'email' => 'legit.buyer@gmail.com',
        ]);

        $engine = app(RiskScoringEngine::class);
        $result = $engine->analyze($order, ['ip_address' => '64.233.160.1']);

        $this->assertLessThanOrEqual(30, $result->total_score);
        $this->assertSame('low', $result->risk_level);
    }

    public function test_scoring_engine_penalizes_high_order_value(): void
    {
        $order = $this->createSampleOrder([
            'total' => '2500.0000',
            'email' => 'rich.buyer@gmail.com',
        ]);

        $engine = app(RiskScoringEngine::class);
        $result = $engine->analyze($order);

        $hasHighValueIndicator = collect($result->indicators)->contains(fn ($i) => $i['key'] === 'high_order_value');
        $this->assertTrue($hasHighValueIndicator);
        $this->assertGreaterThan(0, $result->total_score);
    }

    public function test_scoring_engine_detects_address_mismatch(): void
    {
        $order = $this->createSampleOrder([
            'billing_address' => [
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'line1' => '100 Broadway',
                'city' => 'New York',
                'country_code' => 'US',
            ],
            'shipping_address' => [
                'first_name' => 'Bob',
                'last_name' => 'Jones',
                'line1' => '999 Ocean Dr',
                'city' => 'Miami',
                'country_code' => 'US',
            ],
        ]);

        $engine = app(RiskScoringEngine::class);
        $result = $engine->analyze($order);

        $hasMismatch = collect($result->indicators)->contains(fn ($i) => $i['key'] === 'address_mismatch');
        $this->assertTrue($hasMismatch);
        $this->assertTrue($result->address_mismatch);
    }

    public function test_scoring_engine_detects_blacklisted_ip(): void
    {
        $admin = User::factory()->create();
        BlacklistedIp::query()->create([
            'ip_address' => '203.0.113.195',
            'reason' => 'Known carding network',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $order = $this->createSampleOrder();

        $engine = app(RiskScoringEngine::class);
        $result = $engine->analyze($order, ['ip_address' => '203.0.113.195']);

        $hasBlacklist = collect($result->indicators)->contains(fn ($i) => $i['key'] === 'blacklisted_ip');
        $this->assertTrue($hasBlacklist);
        $this->assertGreaterThanOrEqual(66, $result->total_score);
        $this->assertSame('high', $result->risk_level);
    }

    public function test_scoring_engine_evaluates_custom_rule(): void
    {
        $admin = User::factory()->create();
        FraudRule::query()->create([
            'name' => 'Suspicious cart over 300',
            'type' => 'threshold',
            'field' => 'order_total',
            'operator' => 'greater_than',
            'value' => '300',
            'score_impact' => 45,
            'is_active' => true,
            'priority' => 10,
            'created_by' => $admin->id,
        ]);

        $order = $this->createSampleOrder([
            'total' => '450.0000',
        ]);

        $engine = app(RiskScoringEngine::class);
        $result = $engine->analyze($order);

        $hasCustomRule = collect($result->indicators)->contains(fn ($i) => str_starts_with($i['key'], 'custom_rule_'));
        $this->assertTrue($hasCustomRule);
    }

    public function test_order_placed_event_creates_fraud_score_record(): void
    {
        Mail::fake();

        $order = $this->createSampleOrder([
            'total' => '150.0000',
            'email' => 'shopper@example.com',
        ]);

        event(new OrderPlaced($order->id));

        $this->assertDatabaseHas('fraud_scores', [
            'order_id' => $order->id,
            'customer_email' => 'shopper@example.com',
        ]);
    }

    public function test_admin_can_approve_flagged_order(): void
    {
        $admin = $this->owner();
        $order = $this->createSampleOrder();

        $fraudScore = FraudScore::query()->create([
            'order_id' => $order->id,
            'customer_email' => $order->email,
            'total_score' => 75,
            'risk_level' => 'high',
            'review_status' => FraudScore::STATUS_PENDING,
            'indicators' => [],
            'order_total' => '100.00',
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.modrixcommerce.fraud-detection.queue.approve', $fraudScore),
            ['notes' => 'Customer confirmed identity via phone.']
        );

        $response->assertRedirect();
        $fraudScore->refresh();

        $this->assertSame(FraudScore::STATUS_APPROVED, $fraudScore->review_status);
        $this->assertSame($admin->id, $fraudScore->reviewed_by);
        $this->assertNotNull($fraudScore->reviewed_at);

        $this->assertDatabaseHas('fraud_audit_logs', [
            'action' => 'review.approved',
            'entity_id' => $fraudScore->id,
        ]);
    }

    public function test_admin_can_reject_fraudulent_order(): void
    {
        $admin = $this->owner();
        $order = $this->createSampleOrder();

        $fraudScore = FraudScore::query()->create([
            'order_id' => $order->id,
            'customer_email' => $order->email,
            'total_score' => 90,
            'risk_level' => 'high',
            'review_status' => FraudScore::STATUS_PENDING,
            'indicators' => [],
            'order_total' => '100.00',
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.modrixcommerce.fraud-detection.queue.reject', $fraudScore),
            ['notes' => 'Confirmed chargeback fraud.']
        );

        $response->assertRedirect();
        $fraudScore->refresh();

        $this->assertSame(FraudScore::STATUS_REJECTED, $fraudScore->review_status);
        $this->assertSame($admin->id, $fraudScore->reviewed_by);
    }

    public function test_reports_service_computes_daily_summary_and_export(): void
    {
        $order = $this->createSampleOrder();

        FraudScore::query()->create([
            'order_id' => $order->id,
            'customer_email' => $order->email,
            'total_score' => 80,
            'risk_level' => 'high',
            'review_status' => FraudScore::STATUS_PENDING,
            'indicators' => [
                ['key' => 'high_order_value', 'label' => 'High Order Value', 'score' => 15, 'details' => 'Order > $500'],
            ],
            'order_total' => '650.00',
            'currency' => 'USD',
        ]);

        $reports = app(FraudReportService::class);
        $summary = $reports->getDailySummary();

        $this->assertIsArray($summary);
        $this->assertNotEmpty($summary);

        $dashboard = app(FraudDashboardService::class);
        $stats = $dashboard->getStats();

        $this->assertGreaterThanOrEqual(1, $stats['total_analyzed']);
        $this->assertGreaterThanOrEqual(1, $stats['high_risk_count']);
    }
}
