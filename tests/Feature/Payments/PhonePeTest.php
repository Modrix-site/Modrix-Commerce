<?php

namespace Tests\Feature\Payments;

use App\Core\Extensions\Manifest;
use App\Core\Extensions\ModuleLoader;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Contracts\PaymentGateway;
use App\Domains\Payments\Gateway\InvalidWebhook;
use App\Domains\Payments\Gateway\PaymentUpdateKind;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentMethods;
use EcoModular\PhonePe\PhonePeGateway;
use EcoModular\PhonePe\PhonePeSettings;
use EcoModular\PhonePe\PhonePeSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhonePeTest extends TestCase
{
    use RefreshDatabase;

    private PhonePeSettings $settings;

    private PhonePeGateway $gateway;

    private PhonePeSignature $signature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/phonepe/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        app(ModuleLoader::class)->load('modrixcommerce/phonepe', 'modrixcommerce/phonepe', $manifest);

        $this->settings = app(PhonePeSettings::class);
        $this->gateway = app(PhonePeGateway::class);
        $this->signature = app(PhonePeSignature::class);
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(PhonePeGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('phonepe', $found->code());
        $this->assertSame('phonepe', $found->provider());
    }

    public function test_signature_calculation_and_verification(): void
    {
        $saltKey = 'phonepe_salt_secret_123';
        $saltIndex = '1';
        $endpoint = '/pg/v1/pay';
        $base64Payload = base64_encode(json_encode(['merchantId' => 'M123', 'amount' => 1000]));

        $postSign = $this->signature->signPost($base64Payload, $endpoint, $saltKey, $saltIndex);
        $this->assertStringEndsWith('###1', $postSign);

        $getSign = $this->signature->signGet('/pg/v1/status/M123/T123', $saltKey, $saltIndex);
        $this->assertStringEndsWith('###1', $getSign);

        $webhookPayload = json_encode(['response' => 'test_data']);
        $webhookSig = hash('sha256', $webhookPayload.$saltKey).'###1';

        $this->assertTrue($this->signature->verifyWebhook($webhookPayload, $webhookSig, $saltKey, $saltIndex));
        $this->assertFalse($this->signature->verifyWebhook($webhookPayload, 'tampered###1', $saltKey, $saltIndex));
        $this->assertFalse($this->signature->verifyWebhook($webhookPayload, $webhookSig, $saltKey, '2'));
    }

    public function test_settings_update_encrypts_secrets_and_configures_gateway(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->update(
            enabled: true,
            merchantId: 'PGTESTPAYUAT',
            testMode: true,
            saltIndex: '1',
            label: 'PhonePe UPI & Cards',
            currencies: ['INR'],
            saltKey: 'HIGHLY_CONFIDENTIAL_SALT_KEY',
        );

        $this->assertTrue($this->settings->enabled());
        $this->assertSame('PGTESTPAYUAT', $this->settings->merchantId());
        $this->assertSame('HIGHLY_CONFIDENTIAL_SALT_KEY', $this->settings->saltKey());
        $this->assertSame('1', $this->settings->saltIndex());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertSame('••••••••', $array['salt_key_hint']);
    }

    public function test_session_creation_calls_api_and_returns_phonepe_url(): void
    {
        $this->settings->update(
            enabled: true,
            merchantId: 'PGTESTPAYUAT',
            testMode: true,
            saltIndex: '1',
            label: 'PhonePe',
            currencies: ['INR'],
            saltKey: 'SALT_KEY',
        );

        Http::fake([
            'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/pay' => Http::response([
                'success' => true,
                'code' => 'PAYMENT_INITIATED',
                'message' => 'Payment Initiated',
                'data' => [
                    'merchantId' => 'PGTESTPAYUAT',
                    'merchantTransactionId' => 'PHONEPE-101',
                    'instrumentResponse' => [
                        'type' => 'PAY_PAGE',
                        'redirectInfo' => [
                            'url' => 'https://mercury-uat.phonepe.com/transact/pg?token=mock_phonepe_token_xyz',
                            'method' => 'GET',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-PHONEPE-101',
            'email' => 'buyer@example.in',
            'phone' => '9876543210',
            'currency' => 'INR',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'subtotal' => '299.00',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '299.00',
            'paid_total' => '0.00',
            'refunded_total' => '0.00',
            'payment_method_code' => PhonePeGateway::CODE,
            'payment_method_name' => 'PhonePe',
            'placed_at' => now(),
        ]);
        $order->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => PhonePeGateway::CODE,
            'provider' => PhonePeGateway::CODE,
            'amount' => '299.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertStringStartsWith('PHONEPE-'.$payment->id, $session->reference);
        $this->assertSame('https://mercury-uat.phonepe.com/transact/pg?token=mock_phonepe_token_xyz', $session->redirectUrl);
    }

    public function test_retrieve_queries_status_and_normalizes_payment(): void
    {
        $this->settings->update(
            enabled: true,
            merchantId: 'PGTESTPAYUAT',
            testMode: true,
            saltIndex: '1',
            label: 'PhonePe',
            currencies: ['INR'],
            saltKey: 'SALT_KEY',
        );

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => PhonePeGateway::CODE,
            'provider' => PhonePeGateway::CODE,
            'provider_reference' => 'PHONEPE-TXN-999',
            'amount' => '750.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake([
            'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/status/PGTESTPAYUAT/PHONEPE-TXN-999' => Http::response([
                'success' => true,
                'code' => 'PAYMENT_SUCCESS',
                'message' => 'Your payment is successful.',
                'data' => [
                    'merchantId' => 'PGTESTPAYUAT',
                    'merchantTransactionId' => 'PHONEPE-TXN-999',
                    'transactionId' => 'T2233445566',
                    'amount' => 75000,
                    'paymentInstrument' => ['type' => 'UPI'],
                ],
            ], 200),
        ]);

        $update = $this->gateway->retrieve($payment);

        $this->assertSame(PaymentUpdateKind::Succeeded, $update->kind);
        $this->assertSame('PHONEPE-TXN-999', $update->sessionReference);
        $this->assertSame('T2233445566', $update->paymentReference);
        $this->assertSame('750.00', $update->amount?->toDisplayDecimal());
    }

    public function test_webhook_verification_and_normalization(): void
    {
        $saltKey = 'SALT_SECRET_777';
        $saltIndex = '1';

        $this->settings->update(
            enabled: true,
            merchantId: 'MERCHANT_01',
            testMode: true,
            saltIndex: $saltIndex,
            label: 'PhonePe',
            currencies: ['INR'],
            saltKey: $saltKey,
        );

        $eventData = [
            'success' => true,
            'code' => 'PAYMENT_SUCCESS',
            'message' => 'Payment Success',
            'data' => [
                'merchantId' => 'MERCHANT_01',
                'merchantTransactionId' => 'TXN-WEBHOOK-123',
                'transactionId' => 'PHONEPE-TXN-789',
                'amount' => 35000,
            ],
        ];

        $payload = json_encode($eventData);
        $xVerify = hash('sha256', $payload.$saltKey).'###'.$saltIndex;

        $verified = $this->gateway->verifyWebhook($payload, ['x-verify' => $xVerify]);
        $this->assertSame('PAYMENT_SUCCESS', $verified['code']);

        $normalized = $this->gateway->normalizeEvent($verified);
        $this->assertTrue($normalized->isRelevant());
        $this->assertNotNull($normalized->payment);
        $this->assertSame(PaymentUpdateKind::Succeeded, $normalized->payment->kind);
        $this->assertSame('PHONEPE-TXN-789', $normalized->payment->paymentReference);
        $this->assertSame('TXN-WEBHOOK-123', $normalized->payment->sessionReference);
        $this->assertSame('350.00', $normalized->payment->amount?->toDisplayDecimal());
    }

    public function test_webhook_throws_on_tampered_signature(): void
    {
        $this->settings->update(
            enabled: true,
            merchantId: 'M1',
            testMode: true,
            saltIndex: '1',
            label: 'PhonePe',
            currencies: ['INR'],
            saltKey: 'SALT_KEY',
        );

        $payload = json_encode(['code' => 'PAYMENT_SUCCESS']);

        $this->expectException(InvalidWebhook::class);
        $this->gateway->verifyWebhook($payload, ['x-verify' => 'forged_hash###1']);
    }
}
