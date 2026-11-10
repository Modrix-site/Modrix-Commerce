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
use EcoModular\NGenius\NGeniusAmount;
use EcoModular\NGenius\NGeniusGateway;
use EcoModular\NGenius\NGeniusSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NGeniusTest extends TestCase
{
    use RefreshDatabase;

    private NGeniusSettings $settings;

    private NGeniusGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/ngenius/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        app(ModuleLoader::class)->load('modrixcommerce/ngenius', 'modrixcommerce/ngenius', $manifest);

        $this->settings = app(NGeniusSettings::class);
        $this->gateway = app(NGeniusGateway::class);
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(NGeniusGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('ngenius', $found->code());
        $this->assertSame('ngenius', $found->provider());
    }

    public function test_amount_minor_units_conversion(): void
    {
        // 2 decimals (AED)
        $aed = Money::of('125.50', 'AED');
        $this->assertSame(12550, NGeniusAmount::toMinorUnits($aed));
        $restoredAed = NGeniusAmount::fromMinorUnits(12550, 'AED');
        $this->assertSame('125.50', $restoredAed->toDisplayDecimal());

        // 3 decimals (OMR / KWD)
        $omr = Money::of('50.750', 'OMR');
        $this->assertSame(50750, NGeniusAmount::toMinorUnits($omr));
        $restoredOmr = NGeniusAmount::fromMinorUnits(50750, 'OMR');
        $this->assertSame('50.750', $restoredOmr->toDisplayDecimal());
    }

    public function test_settings_save_and_encryption(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->save([
            'enabled' => true,
            'outlet_id' => 'outlet-uuid-1234-5678',
            'test_mode' => true,
            'action' => 'PURCHASE',
            'label' => 'Network International N-Genius',
            'currencies' => ['AED', 'SAR', 'USD'],
            'api_key' => 'SECRET_SERVICE_API_KEY_999',
            'webhook_secret' => 'WEBHOOK_SECRET_HMAC',
        ]);

        $this->assertTrue($this->settings->enabled());
        $this->assertSame('outlet-uuid-1234-5678', $this->settings->outletId());
        $this->assertSame('SECRET_SERVICE_API_KEY_999', $this->settings->apiKey());
        $this->assertSame('WEBHOOK_SECRET_HMAC', $this->settings->webhookSecret());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertTrue($array['has_api_key']);
        $this->assertTrue($array['has_webhook_secret']);
        $this->assertArrayNotHasKey('api_key', $array); // Ensures secret is not exposed in serialized payload
    }

    public function test_session_creation_requests_token_and_order(): void
    {
        $this->settings->save([
            'enabled' => true,
            'outlet_id' => 'outlet-uuid-123',
            'test_mode' => true,
            'action' => 'PURCHASE',
            'label' => 'Network International',
            'currencies' => ['AED'],
            'api_key' => 'KEY123',
        ]);

        Http::fake([
            'https://api-gateway.sandbox.ngenius-payments.com/identity/auth/access-token' => Http::response([
                'access_token' => 'mock_ngenius_token_abc',
                'expires_in' => 300,
                'token_type' => 'bearer',
            ], 200),
            'https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/outlet-uuid-123/orders' => Http::response([
                '_id' => 'urn:order:123',
                'reference' => 'ng-order-ref-999',
                'action' => 'PURCHASE',
                'amount' => ['currencyCode' => 'AED', 'value' => 50000],
                '_links' => [
                    'payment' => [
                        'href' => 'https://paypage.sandbox.ngenius-payments.com/?payment=mock_session_xyz',
                    ],
                ],
            ], 201),
        ]);

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-NG-101',
            'email' => 'shopper@dubai.ae',
            'phone' => '+971501234567',
            'currency' => 'AED',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'subtotal' => '500.00',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '500.00',
            'paid_total' => '0.00',
            'refunded_total' => '0.00',
            'payment_method_code' => NGeniusGateway::CODE,
            'payment_method_name' => 'Network International',
            'placed_at' => now(),
        ]);
        $order->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => NGeniusGateway::CODE,
            'provider' => NGeniusGateway::CODE,
            'amount' => '500.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertSame('ng-order-ref-999', $session->reference);
        $this->assertSame('https://paypage.sandbox.ngenius-payments.com/?payment=mock_session_xyz', $session->redirectUrl);
    }

    public function test_retrieve_queries_order_and_maps_captured_payment(): void
    {
        $this->settings->save([
            'enabled' => true,
            'outlet_id' => 'outlet-uuid-123',
            'test_mode' => true,
            'api_key' => 'KEY123',
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => NGeniusGateway::CODE,
            'provider' => NGeniusGateway::CODE,
            'provider_reference' => 'ng-order-ref-888',
            'amount' => '350.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake([
            'https://api-gateway.sandbox.ngenius-payments.com/identity/auth/access-token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 300,
            ], 200),
            'https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/outlet-uuid-123/orders/ng-order-ref-888' => Http::response([
                'reference' => 'ng-order-ref-888',
                'action' => 'PURCHASE',
                'amount' => ['currencyCode' => 'AED', 'value' => 35000],
                '_embedded' => [
                    'payment' => [
                        [
                            'reference' => 'ng-pay-ref-777',
                            'state' => 'CAPTURED',
                            'amount' => ['currencyCode' => 'AED', 'value' => 35000],
                            'authResponse' => [
                                'authorizationCode' => '987654',
                                'success' => true,
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $update = $this->gateway->retrieve($payment);

        $this->assertSame(PaymentUpdateKind::Succeeded, $update->kind);
        $this->assertSame('ng-order-ref-888', $update->sessionReference);
        $this->assertSame('ng-pay-ref-777', $update->paymentReference);
        $this->assertNotNull($update->amount);
        $this->assertSame('350.00', $update->amount->toDisplayDecimal());
    }

    public function test_webhook_signature_verification_and_event_normalization(): void
    {
        $this->settings->save([
            'enabled' => true,
            'outlet_id' => 'outlet-uuid-123',
            'webhook_secret' => 'my_webhook_secret_key',
        ]);

        $payload = json_encode([
            'eventName' => 'ORDER_PURCHASED',
            'order' => [
                'reference' => 'ng-order-ref-555',
                'amount' => ['currencyCode' => 'AED', 'value' => 20000],
            ],
            '_embedded' => [
                'payment' => [
                    [
                        'reference' => 'pay-uuid-444',
                        'state' => 'PURCHASED',
                        'amount' => ['currencyCode' => 'AED', 'value' => 20000],
                    ],
                ],
            ],
        ]);

        $validSignature = hash_hmac('sha256', $payload, 'my_webhook_secret_key');

        $verified = $this->gateway->verifyWebhook($payload, ['x-signature' => $validSignature]);
        $this->assertIsArray($verified);
        $this->assertSame('ORDER_PURCHASED', $verified['eventName']);

        $this->expectException(InvalidWebhook::class);
        $this->gateway->verifyWebhook($payload, ['x-signature' => 'invalid_signature_hash']);
    }

    public function test_refund_submits_to_ngenius_and_returns_update(): void
    {
        $this->settings->save([
            'enabled' => true,
            'outlet_id' => 'outlet-uuid-123',
            'test_mode' => true,
            'api_key' => 'KEY123',
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => NGeniusGateway::CODE,
            'provider' => NGeniusGateway::CODE,
            'provider_reference' => 'ng-order-ref-111',
            'provider_payment_id' => 'ng-payment-ref-222',
            'amount' => '400.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PAID,
        ]);

        Http::fake([
            'https://api-gateway.sandbox.ngenius-payments.com/identity/auth/access-token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 300,
            ], 200),
            'https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/outlet-uuid-123/orders/ng-order-ref-111/payments/ng-payment-ref-222/refund' => Http::response([
                'reference' => 'ng-refund-ref-333',
                'state' => 'SUCCESS',
                'amount' => ['currencyCode' => 'AED', 'value' => 15000],
            ], 200),
        ]);

        $refundUpdate = $this->gateway->refund(
            $payment,
            Money::of('150.00', 'AED'),
            'idemp-key-test-123'
        );

        $this->assertSame(RefundUpdate::SUCCEEDED, $refundUpdate->status);
        $this->assertSame('ng-refund-ref-333', $refundUpdate->reference);
        $this->assertSame('ng-payment-ref-222', $refundUpdate->paymentReference);
        $this->assertSame('150.00', $refundUpdate->amount->toDisplayDecimal());
    }
}
