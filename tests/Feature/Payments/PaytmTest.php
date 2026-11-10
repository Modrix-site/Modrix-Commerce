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
use EcoModular\Paytm\PaytmChecksum;
use EcoModular\Paytm\PaytmGateway;
use EcoModular\Paytm\PaytmSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaytmTest extends TestCase
{
    use RefreshDatabase;

    private PaytmSettings $settings;

    private PaytmGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/paytm/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        app(ModuleLoader::class)->load('modrixcommerce/paytm', 'modrixcommerce/paytm', $manifest);

        $this->settings = app(PaytmSettings::class);
        $this->gateway = app(PaytmGateway::class);
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(PaytmGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('paytm', $found->code());
        $this->assertSame('paytm', $found->provider());
    }

    public function test_paytm_checksum_generation_and_verification(): void
    {
        $key = 'mErChAnTkEy12345';
        $params = [
            'MID' => 'TESTMID123',
            'ORDERID' => 'ORD1001',
            'TXNAMOUNT' => '150.00',
        ];

        $signature = PaytmChecksum::generateSignature($params, $key);
        $this->assertNotEmpty($signature);

        $isValid = PaytmChecksum::verifySignature($params, $key, $signature);
        $this->assertTrue($isValid);

        // Verification fails when tampered
        $tampered = $params;
        $tampered['TXNAMOUNT'] = '999.00';
        $this->assertFalse(PaytmChecksum::verifySignature($tampered, $key, $signature));

        // String signature verification
        $strBody = json_encode(['mid' => 'TESTMID123', 'orderId' => 'ORD1001']);
        $strSig = PaytmChecksum::generateSignature($strBody, $key);
        $this->assertTrue(PaytmChecksum::verifySignature($strBody, $key, $strSig));
    }

    public function test_settings_save_and_encryption(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->save([
            'enabled' => true,
            'mid' => 'DIYPAYTM987654321',
            'test_mode' => true,
            'website' => 'WEBSTAGING',
            'label' => 'Paytm UPI & Wallet',
            'currencies' => ['INR'],
            'merchant_key' => 'CONFIDENTIAL_PAYTM_KEY_999',
        ]);

        $this->assertTrue($this->settings->enabled());
        $this->assertSame('DIYPAYTM987654321', $this->settings->mid());
        $this->assertSame('CONFIDENTIAL_PAYTM_KEY_999', $this->settings->merchantKey());
        $this->assertSame('WEBSTAGING', $this->settings->website());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertTrue($array['has_merchant_key']);
        $this->assertArrayNotHasKey('merchant_key', $array);
    }

    public function test_session_creation_initiates_transaction_and_returns_handoff_session(): void
    {
        $this->settings->save([
            'enabled' => true,
            'mid' => 'TESTMID123',
            'test_mode' => true,
            'website' => 'WEBSTAGING',
            'label' => 'Paytm',
            'currencies' => ['INR'],
            'merchant_key' => 'TESTKEY123',
        ]);

        Http::fake([
            'https://securegw-stage.paytm.in/theia/api/v1/initiateTransaction*' => Http::response([
                'head' => [
                    'responseTimestamp' => '1600000000000',
                    'version' => 'v1',
                    'signature' => 'mock_sig',
                ],
                'body' => [
                    'resultInfo' => [
                        'resultStatus' => 'S',
                        'resultCode' => '0000',
                        'resultMsg' => 'Success',
                    ],
                    'txnToken' => 'mock_paytm_txn_token_777',
                ],
            ], 200),
        ]);

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-PTM-101',
            'email' => 'buyer@paytmuser.in',
            'phone' => '9811122233',
            'currency' => 'INR',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'subtotal' => '350.00',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '350.00',
            'paid_total' => '0.00',
            'refunded_total' => '0.00',
            'payment_method_code' => PaytmGateway::CODE,
            'payment_method_name' => 'Paytm',
            'placed_at' => now(),
        ]);
        $order->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => PaytmGateway::CODE,
            'provider' => PaytmGateway::CODE,
            'amount' => '350.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertStringStartsWith('PTM_' . $payment->id, $session->reference);
        $this->assertStringContainsString('mock_paytm_txn_token_777', $session->redirectUrl);
    }

    public function test_retrieve_queries_transaction_status_and_maps_update(): void
    {
        $this->settings->save([
            'enabled' => true,
            'mid' => 'TESTMID123',
            'test_mode' => true,
            'merchant_key' => 'TESTKEY123',
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => PaytmGateway::CODE,
            'provider' => PaytmGateway::CODE,
            'provider_reference' => 'PTM_ORDER_789',
            'amount' => '450.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake([
            'https://securegw-stage.paytm.in/v3/order/status' => Http::response([
                'body' => [
                    'resultInfo' => [
                        'resultStatus' => 'TXN_SUCCESS',
                        'resultCode' => '01',
                        'resultMsg' => 'Txn Successful',
                    ],
                    'txnId' => '20261106111212800110168361623838491',
                    'orderId' => 'PTM_ORDER_789',
                    'txnAmount' => '450.00',
                    'paymentMode' => 'UPI',
                ],
            ], 200),
        ]);

        $update = $this->gateway->retrieve($payment);

        $this->assertSame(PaymentUpdateKind::Succeeded, $update->kind);
        $this->assertSame('PTM_ORDER_789', $update->sessionReference);
        $this->assertSame('20261106111212800110168361623838491', $update->paymentReference);
        $this->assertNotNull($update->amount);
        $this->assertSame('450.00', $update->amount->toDisplayDecimal());
    }

    public function test_webhook_verification_and_event_normalization(): void
    {
        $key = 'TESTKEY123';
        $this->settings->save([
            'enabled' => true,
            'mid' => 'TESTMID123',
            'merchant_key' => $key,
        ]);

        $callbackData = [
            'MID' => 'TESTMID123',
            'ORDERID' => 'PTM_ORDER_555',
            'TXNAMOUNT' => '250.00',
            'STATUS' => 'TXN_SUCCESS',
            'RESPCODE' => '01',
            'RESPMSG' => 'Txn Successful',
            'TXNID' => '202611060000001',
        ];

        $checksum = PaytmChecksum::generateSignature($callbackData, $key);
        $callbackData['CHECKSUMHASH'] = $checksum;

        $payload = json_encode($callbackData);

        $verified = $this->gateway->verifyWebhook($payload, []);
        $this->assertSame('PTM_ORDER_555', $verified['ORDERID']);

        $event = $this->gateway->normalizeEvent($verified);
        $this->assertSame('PTM_ORDER_555', $event->id);
        $this->assertSame(PaymentUpdateKind::Succeeded, $event->payment?->kind);

        // Verification fails with invalid checksum
        $this->expectException(InvalidWebhook::class);
        $callbackData['CHECKSUMHASH'] = 'tampered_checksum';
        $this->gateway->verifyWebhook(json_encode($callbackData), []);
    }

    public function test_refund_submits_to_paytm_and_returns_update(): void
    {
        $this->settings->save([
            'enabled' => true,
            'mid' => 'TESTMID123',
            'test_mode' => true,
            'merchant_key' => 'TESTKEY123',
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => PaytmGateway::CODE,
            'provider' => PaytmGateway::CODE,
            'provider_reference' => 'PTM_ORDER_333',
            'provider_payment_id' => 'TXN_ORIGINAL_444',
            'amount' => '600.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PAID,
        ]);

        Http::fake([
            'https://securegw-stage.paytm.in/refund/apply' => Http::response([
                'body' => [
                    'resultInfo' => [
                        'resultStatus' => 'TXN_SUCCESS',
                        'resultCode' => '10',
                        'resultMsg' => 'Refund request was accepted successfully.',
                    ],
                    'refundId' => 'PAYTM_REF_999',
                    'orderId' => 'PTM_ORDER_333',
                ],
            ], 200),
        ]);

        $refundUpdate = $this->gateway->refund(
            $payment,
            Money::of('200.00', 'INR'),
            'idemp-paytm-ref-123'
        );

        $this->assertSame(RefundUpdate::SUCCEEDED, $refundUpdate->status);
        $this->assertSame('PAYTM_REF_999', $refundUpdate->reference);
        $this->assertSame('TXN_ORIGINAL_444', $refundUpdate->paymentReference);
        $this->assertSame('200.00', $refundUpdate->amount->toDisplayDecimal());
    }
}
