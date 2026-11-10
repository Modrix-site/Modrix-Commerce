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
use App\Domains\Pricing\Money;
use EcoModular\Instamojo\InstamojoGateway;
use EcoModular\Instamojo\InstamojoSettings;
use EcoModular\Instamojo\InstamojoSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstamojoTest extends TestCase
{
    use RefreshDatabase;

    private InstamojoSettings $settings;

    private InstamojoGateway $gateway;

    private InstamojoSignature $signature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/instamojo/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        app(ModuleLoader::class)->load('modrixcommerce/instamojo', 'modrixcommerce/instamojo', $manifest);

        $this->settings = app(InstamojoSettings::class);
        $this->gateway = app(InstamojoGateway::class);
        $this->signature = app(InstamojoSignature::class);
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(InstamojoGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('instamojo', $found->code());
        $this->assertSame('instamojo', $found->provider());
    }

    public function test_mac_calculation_and_verification(): void
    {
        $salt = 'private_salt_test_123';
        $params = [
            'payment_id' => 'MOJO1001',
            'status' => 'Credit',
            'amount' => '350.00',
            'buyer_name' => 'Alice',
        ];

        $mac = $this->signature->calculate($params, $salt);
        $this->assertNotEmpty($mac);

        $params['mac'] = $mac;
        $this->assertTrue($this->signature->verify($params, $salt));

        // Tampered payload fails verification
        $tampered = $params;
        $tampered['amount'] = '999.00';
        $this->assertFalse($this->signature->verify($tampered, $salt));
    }

    public function test_settings_save_and_encryption(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->save([
            'enabled' => true,
            'test_mode' => true,
            'label' => 'Instamojo Payments',
            'currencies' => ['INR'],
            'api_key' => 'TEST_API_KEY_123',
            'auth_token' => 'TEST_AUTH_TOKEN_456',
            'private_salt' => 'TEST_PRIVATE_SALT_789',
        ]);

        $this->assertTrue($this->settings->enabled());
        $this->assertSame('TEST_API_KEY_123', $this->settings->apiKey());
        $this->assertSame('TEST_AUTH_TOKEN_456', $this->settings->authToken());
        $this->assertSame('TEST_PRIVATE_SALT_789', $this->settings->privateSalt());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertTrue($array['has_api_key']);
        $this->assertTrue($array['has_auth_token']);
        $this->assertTrue($array['has_private_salt']);
        $this->assertArrayNotHasKey('api_key', $array);
        $this->assertArrayNotHasKey('auth_token', $array);
    }

    public function test_session_creation_creates_payment_request_and_returns_session(): void
    {
        $this->settings->save([
            'enabled' => true,
            'test_mode' => true,
            'api_key' => 'API_KEY',
            'auth_token' => 'AUTH_TOKEN',
            'currencies' => ['INR'],
        ]);

        Http::fake([
            'https://test.instamojo.com/api/1.1/payment-requests/' => Http::response([
                'success' => true,
                'payment_request' => [
                    'id' => 'req_mojo_12345',
                    'amount' => '450.00',
                    'status' => 'Pending',
                    'longurl' => 'https://test.instamojo.com/@store/req_mojo_12345',
                ],
            ], 200),
        ]);

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-MOJO-101',
            'email' => 'buyer@instamojouser.in',
            'phone' => '9876543210',
            'currency' => 'INR',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'subtotal' => '450.00',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '450.00',
            'paid_total' => '0.00',
            'refunded_total' => '0.00',
            'payment_method_code' => InstamojoGateway::CODE,
            'payment_method_name' => 'Instamojo',
            'placed_at' => now(),
        ]);
        $order->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => InstamojoGateway::CODE,
            'provider' => InstamojoGateway::CODE,
            'amount' => '450.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertSame('req_mojo_12345', $session->reference);
        $this->assertSame('https://test.instamojo.com/@store/req_mojo_12345', $session->redirectUrl);
    }

    public function test_retrieve_queries_payment_request_and_normalizes_payment(): void
    {
        $this->settings->save([
            'enabled' => true,
            'test_mode' => true,
            'api_key' => 'API_KEY',
            'auth_token' => 'AUTH_TOKEN',
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => InstamojoGateway::CODE,
            'provider' => InstamojoGateway::CODE,
            'provider_reference' => 'req_mojo_999888',
            'amount' => '650.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake([
            'https://test.instamojo.com/api/1.1/payment-requests/req_mojo_999888/' => Http::response([
                'success' => true,
                'payment_request' => [
                    'id' => 'req_mojo_999888',
                    'status' => 'Completed',
                    'amount' => '650.00',
                    'payments' => [
                        [
                            'payment_id' => 'MOJO_PAY_123456',
                            'status' => 'Credit',
                            'amount' => '650.00',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $update = $this->gateway->retrieve($payment);

        $this->assertSame(PaymentUpdateKind::Succeeded, $update->kind);
        $this->assertSame('req_mojo_999888', $update->sessionReference);
        $this->assertSame('MOJO_PAY_123456', $update->paymentReference);
        $this->assertNotNull($update->amount);
        $this->assertSame('650.00', $update->amount->toDisplayDecimal());
    }

    public function test_webhook_verification_and_event_normalization(): void
    {
        $salt = 'private_salt_test';
        $this->settings->save([
            'enabled' => true,
            'api_key' => 'KEY',
            'auth_token' => 'TOKEN',
            'private_salt' => $salt,
        ]);

        $payloadData = [
            'payment_id' => 'MOJO_PAY_888',
            'payment_request_id' => 'req_mojo_888',
            'status' => 'Credit',
            'amount' => '300.00',
            'buyer_name' => 'Bob',
        ];

        $mac = $this->signature->calculate($payloadData, $salt);
        $payloadData['mac'] = $mac;

        $payload = json_encode($payloadData);

        $verified = $this->gateway->verifyWebhook($payload, []);
        $this->assertSame('req_mojo_888', $verified['payment_request_id']);

        $event = $this->gateway->normalizeEvent($verified);
        $this->assertSame('req_mojo_888', $event->id);
        $this->assertSame(PaymentUpdateKind::Succeeded, $event->payment?->kind);
        $this->assertSame('MOJO_PAY_888', $event->payment?->paymentReference);

        // Verification fails with tampered mac
        $this->expectException(InvalidWebhook::class);
        $payloadData['mac'] = 'tampered_mac';
        $this->gateway->verifyWebhook(json_encode($payloadData), []);
    }

    public function test_refund_submits_to_instamojo_and_returns_update(): void
    {
        $this->settings->save([
            'enabled' => true,
            'test_mode' => true,
            'api_key' => 'KEY',
            'auth_token' => 'TOKEN',
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => InstamojoGateway::CODE,
            'provider' => InstamojoGateway::CODE,
            'provider_reference' => 'req_mojo_555',
            'provider_payment_id' => 'MOJO_PAY_777',
            'amount' => '500.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PAID,
        ]);

        Http::fake([
            'https://test.instamojo.com/api/1.1/refunds/' => Http::response([
                'success' => true,
                'refund' => [
                    'id' => 'REF_MOJO_333',
                    'payment_id' => 'MOJO_PAY_777',
                    'status' => 'Refunded',
                    'type' => 'RFD',
                    'refund_amount' => '200.00',
                    'total_amount' => '200.00',
                ],
            ], 200),
        ]);

        $refundUpdate = $this->gateway->refund(
            $payment,
            Money::of('200.00', 'INR'),
            'idemp-mojo-ref-123'
        );

        $this->assertSame(RefundUpdate::SUCCEEDED, $refundUpdate->status);
        $this->assertSame('REF_MOJO_333', $refundUpdate->reference);
        $this->assertSame('MOJO_PAY_777', $refundUpdate->paymentReference);
        $this->assertSame('200.00', $refundUpdate->amount->toDisplayDecimal());
    }
}
