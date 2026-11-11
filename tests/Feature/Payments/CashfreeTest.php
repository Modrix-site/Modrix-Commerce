<?php

namespace Tests\Feature\Payments;

use App\Core\Extensions\Manifest;
use App\Core\Extensions\ModuleLoader;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Contracts\PaymentGateway;
use App\Domains\Payments\Gateway\InvalidWebhook;
use App\Domains\Payments\Gateway\PaymentUpdateKind;
use App\Domains\Payments\Gateway\RefundUpdate;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentMethods;
use EcoModular\Cashfree\CashfreeGateway;
use EcoModular\Cashfree\CashfreeSettings;
use EcoModular\Cashfree\CashfreeSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CashfreeTest extends TestCase
{
    use RefreshDatabase;

    private CashfreeSettings $settings;

    private CashfreeGateway $gateway;

    private CashfreeSignature $signature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/cashfree/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        app(ModuleLoader::class)->load('modrixcommerce/cashfree', 'modrixcommerce/cashfree', $manifest);

        $this->settings = app(CashfreeSettings::class);
        $this->gateway = app(CashfreeGateway::class);
        $this->signature = app(CashfreeSignature::class);
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(CashfreeGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('cashfree', $found->code());
        $this->assertSame('cashfree', $found->provider());
    }

    public function test_signature_calculation_and_verification(): void
    {
        $secretKey = 'cf_secret_key_test_123';
        $timestamp = (string) time();
        $payload = json_encode(['data' => ['order' => ['order_id' => 'CF-100']]]);

        $calculated = $this->signature->calculate($payload, $timestamp, $secretKey);
        $this->assertNotEmpty($calculated);

        $this->assertTrue($this->signature->verify($payload, $calculated, $timestamp, $secretKey));
        $this->assertFalse($this->signature->verify($payload, 'forged_sig', $timestamp, $secretKey));
        $this->assertFalse($this->signature->verify($payload, $calculated, (string) (time() + 100), $secretKey));
    }

    public function test_settings_update_encrypts_secrets_and_configures_gateway(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->update(
            enabled: true,
            appId: 'TEST_CF_APP_001',
            testMode: true,
            label: 'Cashfree UPI & Cards',
            currencies: ['INR', 'USD'],
            secretKey: 'HIGHLY_SECRET_CF_KEY',
        );

        $this->assertTrue($this->settings->enabled());
        $this->assertSame('TEST_CF_APP_001', $this->settings->appId());
        $this->assertSame('HIGHLY_SECRET_CF_KEY', $this->settings->secretKey());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertSame('••••••••', $array['secret_key_hint']);
    }

    public function test_session_creation_calls_api_and_caches_session_id(): void
    {
        $this->settings->update(
            enabled: true,
            appId: 'TEST_CF_APP_001',
            testMode: true,
            label: 'Cashfree Payments',
            currencies: ['INR'],
            secretKey: 'SECRET_KEY',
        );

        Http::fake([
            'https://sandbox.cashfree.com/pg/orders' => Http::response([
                'cf_order_id' => '12345678',
                'order_id' => 'CF-TEST-1',
                'payment_session_id' => 'session_mock_abc123',
                'order_status' => 'ACTIVE',
            ], 200),
        ]);

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-CF-101',
            'email' => 'buyer@example.in',
            'phone' => '9876543210',
            'currency' => 'INR',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'subtotal' => '499.00',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '499.00',
            'paid_total' => '0.00',
            'refunded_total' => '0.00',
            'payment_method_code' => CashfreeGateway::CODE,
            'payment_method_name' => 'Cashfree Payments',
            'placed_at' => now(),
        ]);
        $order->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => CashfreeGateway::CODE,
            'provider' => CashfreeGateway::CODE,
            'amount' => '499.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertStringStartsWith('CF-'.$payment->id, $session->reference);
        $this->assertStringContainsString('/m/modrixcommerce/cashfree/pay/'.$payment->id, $session->redirectUrl);

        $cached = Cache::get("cashfree:handoff:{$payment->id}");
        $this->assertIsArray($cached);
        $this->assertSame('session_mock_abc123', $cached['paymentSessionId']);
        $this->assertSame('sandbox', $cached['mode']);
        $this->assertSame('499.00', $cached['amount']);
        $this->assertSame('INR', $cached['currency']);
    }

    public function test_webhook_verification_and_normalization(): void
    {
        $secretKey = 'SEC_KEY_999';

        $this->settings->update(
            enabled: true,
            appId: 'APP_1',
            testMode: true,
            label: 'Cashfree',
            currencies: ['INR'],
            secretKey: $secretKey,
        );

        $payload = json_encode([
            'type' => 'PAYMENT_SUCCESS_WEBHOOK',
            'data' => [
                'order' => [
                    'order_id' => 'CF-ORD-77',
                    'order_amount' => '999.00',
                    'order_currency' => 'INR',
                ],
                'payment' => [
                    'cf_payment_id' => '22446688',
                    'payment_status' => 'SUCCESS',
                    'payment_amount' => '999.00',
                    'payment_currency' => 'INR',
                ],
            ],
        ]);

        $timestamp = (string) time();
        $signature = $this->signature->calculate($payload, $timestamp, $secretKey);

        $headers = [
            'x-webhook-timestamp' => $timestamp,
            'x-webhook-signature' => $signature,
        ];

        $verified = $this->gateway->verifyWebhook($payload, $headers);
        $this->assertSame('PAYMENT_SUCCESS_WEBHOOK', $verified['type']);

        $normalized = $this->gateway->normalizeEvent($verified);
        $this->assertTrue($normalized->isRelevant());
        $this->assertNotNull($normalized->payment);
        $this->assertSame(PaymentUpdateKind::Succeeded, $normalized->payment->kind);
        $this->assertSame('22446688', $normalized->payment->paymentReference);
        $this->assertSame('CF-ORD-77', $normalized->payment->sessionReference);
        $this->assertSame('999.00', $normalized->payment->amount?->toDisplayDecimal());
    }

    public function test_webhook_throws_on_tampered_signature(): void
    {
        $this->settings->update(
            enabled: true,
            appId: 'APP_1',
            testMode: true,
            label: 'Cashfree',
            currencies: ['INR'],
            secretKey: 'SEC_KEY_123',
        );

        $payload = json_encode(['type' => 'PAYMENT_SUCCESS_WEBHOOK']);

        $this->expectException(InvalidWebhook::class);
        $this->gateway->verifyWebhook($payload, [
            'x-webhook-timestamp' => (string) time(),
            'x-webhook-signature' => 'FORGED_SIGNATURE',
        ]);
    }

    public function test_refund_webhook_normalization(): void
    {
        $event = [
            'type' => 'REFUND_STATUS_WEBHOOK',
            'data' => [
                'refund' => [
                    'cf_refund_id' => 'REF_112233',
                    'order_id' => 'CF-ORD-55',
                    'refund_status' => 'SUCCESS',
                    'refund_amount' => '250.00',
                    'refund_currency' => 'INR',
                ],
            ],
        ];

        $normalized = $this->gateway->normalizeEvent($event);
        $this->assertTrue($normalized->isRelevant());
        $this->assertNotNull($normalized->refund);
        $this->assertSame('REF_112233', $normalized->refund->reference);
        $this->assertSame('CF-ORD-55', $normalized->refund->paymentReference);
        $this->assertSame(RefundUpdate::SUCCEEDED, $normalized->refund->status);
        $this->assertSame('250.00', $normalized->refund->amount->toDisplayDecimal());
    }
}
