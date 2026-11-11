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
use EcoModular\PayBy\PaybyGateway;
use EcoModular\PayBy\PaybySettings;
use EcoModular\PayBy\PaybySignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaybyTest extends TestCase
{
    use RefreshDatabase;

    private PaybySettings $settings;

    private PaybyGateway $gateway;

    private PaybySignature $signature;

    private string $testPrivateKey;

    private string $testPublicKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/payby/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        app(ModuleLoader::class)->load('modrixcommerce/payby', 'modrixcommerce/payby', $manifest);

        $this->settings = app(PaybySettings::class);
        $this->gateway = app(PaybyGateway::class);
        $this->signature = app(PaybySignature::class);

        // Pre-generated valid 2048-bit RSA key pair for testing
        $this->testPrivateKey = "-----BEGIN PRIVATE KEY-----\n".
            "MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCV/zANYt39Oif1\n".
            "ZnCpDTbo852H9uuS/CS9P2MsvSjVwGDS8KWFAdCUuQ4YXSY5ULK/go46KiZ0FAK1\n".
            "C4tQb1KDfEdRJpH6jsMxoY4dQhJcDpwd+28gAtWQ9Bc1aruAoe2Kaqs9T46a4h1N\n".
            "rR3UFrMZMQEyy9SQ/udt/Qh+Qna/oNO4WJ6VXOa6GSjni5uLEOeBBuUGUgcZN0NT\n".
            "EqXja3dhVsiiNyhHz2mUpQeYQ9rUBG0TAkVgqB3mt+ReQt5cTdwpnYCFyAGeSRTH\n".
            "2BGCd+RtMZk6NtSWyxIoYEMQVW4Tx2K5NGi0b4+K+cak9lGJNG+f7hLi1SiPFquz\n".
            "+v3On+n7AgMBAAECggEANxyRe8GhPcrx5pUSfNKZo8Lq1QuQ56sUFqKlHkmMKOlr\n".
            "yeVYc4PErvhFaQdEpZd0bbAa8N0OzuSRUBLjR5T3dM8jZvosdaqhpmktDav6ke9e\n".
            "Rhfdbx4LeRxuMJa8ONmGLS7fJwJFq7u7t5ANXyFOAcoDHjZNLk8yGjP3vGhQODzf\n".
            "t+YXciuut7i/Pl7x32oBd4/oO1OfWBMvUkwkhdGSsa2jwIh8CrLCcLo9U3ZR1kXw\n".
            "qClZiNlLCDCuonaB5fuldl7Ba239zCrTk3O1yM4mbPPme3euQAd0+M9cVFUhq1C+\n".
            "JHzoXVba0dZ1bRunRcAbc4RFn2NtVucAHX7FvY7JSQKBgQDHi7jR+kRe53BfN/UN\n".
            "dpgIoP7rXF8pX9JncM2k3/tIWNvUEpNnNTl0saTDc3MWpGqSIgg5ahgThAPuFg++\n".
            "R1vR+4HHglApxaU0wa+YLr07jjCKIdsegsZiMmXuab5wx+SQ1+XVLmjOjiFn74N7\n".
            "PZcuKMmgAb83iFO+iA9Mx5Fn+QKBgQDAbtbCmqtQmqnycLeOyHwzE1LsgUTdWvW2\n".
            "qdEiOftaxge6IlXQcZiIgoYnHMw+q33dBiTWE1VMOe81KQLGY8LhFYVPqh9UHvMt\n".
            "hOBqsVWdq3b3vpxy+xtLjJ3ctcGH+pwJPa2BjGclxDa8ujB6EumoEBmZDhlHATgF\n".
            "Y9qasVRmkwKBgHeES1WQ9WR2cIrHhEHlF1iLvUORFvo8B1SnbrRi5ynE9x3FZn45\n".
            "lck8IThjrLoSDjaqpYxl7De8KXJvykwoh2l0n520gnkShUnc/GRFElhaaN5DOu/C\n".
            "Wjo0f3LV6Re50j+i4GjYHDQjUwTiozisgiFGksUAZtxHDgmfirJtEb1ZAoGBAIxX\n".
            "SaEblB1w7si1k+k6JfpJ+YwqqsKU9+aD6tX/W2VUT4ZcTCF89zV3m8cipYJ/J4fu\n".
            "VycZanfxOJeYlUGCuDnXrCXxeG+tMsTbx5+IPVyEFHuSNB9XKwzqPKcel0pIZ0wG\n".
            "A2gbsY5Fj3YMhBZDfMzpEQIi2iSCqwyjSmbPNTKpAoGBALIu+sVOiLPT6OdXxm70\n".
            "pfGspuxyA7ReRccWo/bBHhjEVYijHysab1oJMSWwraAMwBNsAg16WcLEjQ5x2zS8\n".
            "cUYOp8w5wCedJmvrub1dQXREOnPGgJTjOgFqcfHOQ0yqDWgccGeT6q5ZnGuFiPJh\n".
            "NiIohTW7EzUxu1gnGUwZgU3M\n".
            '-----END PRIVATE KEY-----';

        $this->testPublicKey = "-----BEGIN PUBLIC KEY-----\n".
            "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAlf8wDWLd/Ton9WZwqQ02\n".
            "6POdh/brkvwkvT9jLL0o1cBg0vClhQHQlLkOGF0mOVCyv4KOOiomdBQCtQuLUG9S\n".
            "g3xHUSaR+o7DMaGOHUISXA6cHftvIALVkPQXNWq7gKHtimqrPU+OmuIdTa0d1Baz\n".
            "GTEBMsvUkP7nbf0IfkJ2v6DTuFielVzmuhko54ubixDngQblBlIHGTdDUxKl42t3\n".
            "YVbIojcoR89plKUHmEPa1ARtEwJFYKgd5rfkXkLeXE3cKZ2AhcgBnkkUx9gRgnfk\n".
            "bTGZOjbUlssSKGBDEFVuE8diuTRotG+PivnGpPZRiTRvn+4S4tUojxars/r9zp/p\n".
            "+wIDAQAB\n".
            '-----END PUBLIC KEY-----';
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(PaybyGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('payby', $found->code());
        $this->assertSame('payby', $found->provider());
    }

    public function test_rsa_signature_generation_and_verification(): void
    {
        $payload = json_encode(['requestTime' => 1700000000000, 'bizContent' => ['merchantOrderNo' => 'ORD1001']]);

        $signature = $this->signature->sign($payload, $this->testPrivateKey);
        $this->assertNotEmpty($signature);

        $isValid = $this->signature->verify($payload, $signature, $this->testPublicKey);
        $this->assertTrue($isValid);

        // Verification fails if payload is tampered
        $tampered = json_encode(['requestTime' => 1700000000000, 'bizContent' => ['merchantOrderNo' => 'ORD9999']]);
        $this->assertFalse($this->signature->verify($tampered, $signature, $this->testPublicKey));
    }

    public function test_settings_save_and_encryption(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->save([
            'enabled' => true,
            'partner_id' => '200000018128',
            'test_mode' => true,
            'label' => 'PayBy UAE',
            'currencies' => ['AED', 'USD'],
            'private_key' => $this->testPrivateKey,
            'payby_public_key' => $this->testPublicKey,
        ]);

        $this->assertTrue($this->settings->enabled());
        $this->assertSame('200000018128', $this->settings->partnerId());
        $this->assertSame($this->testPrivateKey, $this->settings->privateKey());
        $this->assertSame($this->testPublicKey, $this->settings->paybyPublicKey());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertTrue($array['has_private_key']);
        $this->assertTrue($array['has_payby_public_key']);
        $this->assertArrayNotHasKey('private_key', $array);
        $this->assertArrayNotHasKey('payby_public_key', $array);
    }

    public function test_session_creation_places_order_and_returns_session(): void
    {
        $this->settings->save([
            'enabled' => true,
            'partner_id' => '200000018128',
            'test_mode' => true,
            'currencies' => ['AED'],
            'private_key' => $this->testPrivateKey,
        ]);

        Http::fake([
            'https://uat.test2pay.com/sgs/api/acquire2/placeOrder' => Http::response([
                'head' => [
                    'respCode' => 'SUCCESS',
                    'respMsg' => 'success',
                ],
                'body' => [
                    'orderNo' => 'PB_ORDER_123456789',
                    'tokenUrl' => 'https://pay.payby.com/checkout/mock_session_abc',
                ],
            ], 200),
        ]);

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-PB-101',
            'email' => 'buyer@paybyuser.ae',
            'phone' => '+971501234567',
            'currency' => 'AED',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'subtotal' => '200.00',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '200.00',
            'paid_total' => '0.00',
            'refunded_total' => '0.00',
            'payment_method_code' => PaybyGateway::CODE,
            'payment_method_name' => 'PayBy',
            'placed_at' => now(),
        ]);
        $order->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => PaybyGateway::CODE,
            'provider' => PaybyGateway::CODE,
            'amount' => '200.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertStringStartsWith('PAYBY_'.$payment->id, $session->reference);
        $this->assertSame('https://pay.payby.com/checkout/mock_session_abc', $session->redirectUrl);
    }

    public function test_retrieve_queries_order_and_normalizes_payment(): void
    {
        $this->settings->save([
            'enabled' => true,
            'partner_id' => '200000018128',
            'test_mode' => true,
            'private_key' => $this->testPrivateKey,
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => PaybyGateway::CODE,
            'provider' => PaybyGateway::CODE,
            'provider_reference' => 'PAYBY_ORD_999',
            'amount' => '500.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake([
            'https://uat.test2pay.com/sgs/api/acquire2/order/query' => Http::response([
                'head' => [
                    'respCode' => 'SUCCESS',
                    'respMsg' => 'success',
                ],
                'body' => [
                    'orderNo' => 'PB_99887766',
                    'merchantOrderNo' => 'PAYBY_ORD_999',
                    'orderStatus' => 'PAID',
                    'totalAmount' => [
                        'amount' => 500.00,
                        'currency' => 'AED',
                    ],
                ],
            ], 200),
        ]);

        $update = $this->gateway->retrieve($payment);

        $this->assertSame(PaymentUpdateKind::Succeeded, $update->kind);
        $this->assertSame('PAYBY_ORD_999', $update->sessionReference);
        $this->assertSame('PB_99887766', $update->paymentReference);
        $this->assertNotNull($update->amount);
        $this->assertSame('500.00', $update->amount->toDisplayDecimal());
    }

    public function test_webhook_verification_and_event_normalization(): void
    {
        $this->settings->save([
            'enabled' => true,
            'partner_id' => '200000018128',
            'private_key' => $this->testPrivateKey,
            'payby_public_key' => $this->testPublicKey,
        ]);

        $payloadData = [
            'head' => ['respCode' => 'SUCCESS'],
            'bizContent' => [
                'merchantOrderNo' => 'PAYBY_ORD_777',
                'orderNo' => 'PB_112233',
                'orderStatus' => 'PAID',
                'totalAmount' => ['amount' => 350.00, 'currency' => 'AED'],
            ],
        ];

        $jsonPayload = json_encode($payloadData);
        $signature = $this->signature->sign($jsonPayload, $this->testPrivateKey);

        $verified = $this->gateway->verifyWebhook($jsonPayload, ['sign' => $signature]);
        $this->assertSame('PAYBY_ORD_777', $verified['bizContent']['merchantOrderNo']);

        $event = $this->gateway->normalizeEvent($verified);
        $this->assertSame('PAYBY_ORD_777', $event->id);
        $this->assertSame(PaymentUpdateKind::Succeeded, $event->payment?->kind);
        $this->assertSame('PB_112233', $event->payment?->paymentReference);

        // Verification fails with invalid sign
        $this->expectException(InvalidWebhook::class);
        $this->gateway->verifyWebhook($jsonPayload, ['sign' => base64_encode('fake_invalid_sig')]);
    }

    public function test_refund_submits_to_payby_and_returns_update(): void
    {
        $this->settings->save([
            'enabled' => true,
            'partner_id' => '200000018128',
            'test_mode' => true,
            'private_key' => $this->testPrivateKey,
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => PaybyGateway::CODE,
            'provider' => PaybyGateway::CODE,
            'provider_reference' => 'PAYBY_ORD_444',
            'amount' => '400.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PAID,
        ]);

        Http::fake([
            'https://uat.test2pay.com/sgs/api/acquire2/refund/placeOrder' => Http::response([
                'head' => [
                    'respCode' => 'SUCCESS',
                    'respMsg' => 'Refund processed',
                ],
                'body' => [
                    'refundOrderNo' => 'REF_PB_888',
                    'status' => 'SUCCESS',
                ],
            ], 200),
        ]);

        $refundUpdate = $this->gateway->refund(
            $payment,
            Money::of('150.00', 'AED'),
            'idemp-payby-ref-123'
        );

        $this->assertSame(RefundUpdate::SUCCEEDED, $refundUpdate->status);
        $this->assertSame('REF_PB_888', $refundUpdate->reference);
        $this->assertSame('PAYBY_ORD_444', $refundUpdate->paymentReference);
        $this->assertSame('150.00', $refundUpdate->amount->toDisplayDecimal());
    }
}
