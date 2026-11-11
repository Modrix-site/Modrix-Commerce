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
use EcoModular\PayU\PayuGateway;
use EcoModular\PayU\PayuSettings;
use EcoModular\PayU\PayuSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayuTest extends TestCase
{
    use RefreshDatabase;

    private PayuSettings $settings;

    private PayuGateway $gateway;

    private PayuSignature $signature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/payu/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        app(ModuleLoader::class)->load('modrixcommerce/payu', 'modrixcommerce/payu', $manifest);

        $this->settings = app(PayuSettings::class);
        $this->gateway = app(PayuGateway::class);
        $this->signature = app(PayuSignature::class);
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(PayuGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('payu', $found->code());
        $this->assertSame('payu', $found->provider());
    }

    public function test_sha512_signature_calculation_and_reverse_verification(): void
    {
        $key = 'gtKFFx';
        $salt = 'eCwWELxi';
        $txnid = 'TXN1001';
        $amount = '10.00';
        $productinfo = 'Test Product';
        $firstname = 'John';
        $email = 'john@example.com';

        // 1. Request Hash (11 pipes between email and salt for empty udf1..udf10)
        $expectedSeq = "$key|$txnid|$amount|$productinfo|$firstname|$email|||||||||||$salt";
        $expectedHash = strtolower(hash('sha512', $expectedSeq));

        $hash = $this->signature->generateRequestHash(
            $key,
            $txnid,
            $amount,
            $productinfo,
            $firstname,
            $email,
            $salt
        );

        $this->assertSame($expectedHash, $hash);

        // 2. Reverse Response Hash (Standard: 11 pipes between status and email for empty udf10..udf1)
        $responseParams = [
            'key' => $key,
            'txnid' => $txnid,
            'amount' => $amount,
            'productinfo' => $productinfo,
            'firstname' => $firstname,
            'email' => $email,
            'status' => 'success',
            'udf1' => '',
            'udf2' => '',
            'udf3' => '',
            'udf4' => '',
            'udf5' => '',
        ];

        $reverseSeq = "$salt|success|||||||||||$email|$firstname|$productinfo|$amount|$txnid|$key";
        $responseHash = strtolower(hash('sha512', $reverseSeq));
        $responseParams['hash'] = $responseHash;

        $this->assertTrue($this->signature->verifyResponseHash($responseParams, $salt));

        // Tampered payload fails
        $tampered = $responseParams;
        $tampered['amount'] = '500.00';
        $this->assertFalse($this->signature->verifyResponseHash($tampered, $salt));

        // 3. Command Hash
        $cmdHash = $this->signature->generateCommandHash($key, 'verify_payment', $txnid, $salt);
        $this->assertSame(strtolower(hash('sha512', "$key|verify_payment|$txnid|$salt")), $cmdHash);
    }

    public function test_settings_save_and_encryption(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->save([
            'enabled' => true,
            'merchant_key' => 'TEST_KEY_123',
            'test_mode' => true,
            'label' => 'PayU India',
            'currencies' => ['INR'],
            'salt' => 'HIGHLY_SECRET_SALT_999',
        ]);

        $this->assertTrue($this->settings->enabled());
        $this->assertSame('TEST_KEY_123', $this->settings->merchantKey());
        $this->assertSame('HIGHLY_SECRET_SALT_999', $this->settings->salt());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertTrue($array['has_salt']);
        $this->assertArrayNotHasKey('salt', $array);
    }

    public function test_session_creation_generates_hash_and_caches_handoff(): void
    {
        $this->settings->save([
            'enabled' => true,
            'merchant_key' => 'MY_KEY',
            'test_mode' => true,
            'label' => 'PayU',
            'currencies' => ['INR'],
            'salt' => 'MY_SALT',
        ]);

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-PAYU-101',
            'email' => 'customer@payu.in',
            'phone' => '9988776655',
            'currency' => 'INR',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'subtotal' => '250.00',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '250.00',
            'paid_total' => '0.00',
            'refunded_total' => '0.00',
            'payment_method_code' => PayuGateway::CODE,
            'payment_method_name' => 'PayU',
            'placed_at' => now(),
        ]);
        $order->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => PayuGateway::CODE,
            'provider' => PayuGateway::CODE,
            'amount' => '250.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertStringStartsWith('PAYU_'.$payment->id, $session->reference);
        $this->assertStringContainsString('handoff/'.$payment->id, $session->redirectUrl);

        $cached = Cache::get("payu:handoff:{$payment->id}");
        $this->assertIsArray($cached);
        $this->assertSame('https://test.payu.in/_payment', $cached['action']);
        $this->assertSame('MY_KEY', $cached['fields']['key']);
        $this->assertSame('250.00', $cached['fields']['amount']);
        $this->assertNotEmpty($cached['fields']['hash']);
    }

    public function test_retrieve_queries_postservice_and_normalizes_payment(): void
    {
        $this->settings->save([
            'enabled' => true,
            'merchant_key' => 'MY_KEY',
            'test_mode' => true,
            'salt' => 'MY_SALT',
        ]);

        $txnid = 'PAYU_TXN_555';

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => PayuGateway::CODE,
            'provider' => PayuGateway::CODE,
            'provider_reference' => $txnid,
            'amount' => '400.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake([
            'https://test.payu.in/merchant/postservice.php*' => Http::response([
                'status' => 1,
                'msg' => '1 out of 1 Transactions Fetched Successfully',
                'transaction_details' => [
                    $txnid => [
                        'mihpayid' => '403993715537142566',
                        'status' => 'success',
                        'amount' => '400.00',
                        'error_code' => 'E000',
                    ],
                ],
            ], 200),
        ]);

        $update = $this->gateway->retrieve($payment);

        $this->assertSame(PaymentUpdateKind::Succeeded, $update->kind);
        $this->assertSame($txnid, $update->sessionReference);
        $this->assertSame('403993715537142566', $update->paymentReference);
        $this->assertNotNull($update->amount);
        $this->assertSame('400.00', $update->amount->toDisplayDecimal());
    }

    public function test_webhook_verification_and_event_normalization(): void
    {
        $key = 'MY_KEY';
        $salt = 'MY_SALT';
        $txnid = 'PAYU_TXN_888';

        $this->settings->save([
            'enabled' => true,
            'merchant_key' => $key,
            'salt' => $salt,
        ]);

        $params = [
            'key' => $key,
            'txnid' => $txnid,
            'amount' => '500.00',
            'productinfo' => 'Order 101',
            'firstname' => 'Alice',
            'email' => 'alice@test.in',
            'status' => 'success',
            'mihpayid' => '9988112233',
            'udf1' => '',
            'udf2' => '',
            'udf3' => '',
            'udf4' => '',
            'udf5' => '',
        ];

        $reverseSeq = "$salt|success|||||||||||alice@test.in|Alice|Order 101|500.00|$txnid|$key";
        $params['hash'] = strtolower(hash('sha512', $reverseSeq));

        $payload = json_encode($params);

        $verified = $this->gateway->verifyWebhook($payload, []);
        $this->assertSame($txnid, $verified['txnid']);

        $event = $this->gateway->normalizeEvent($verified);
        $this->assertSame($txnid, $event->id);
        $this->assertSame(PaymentUpdateKind::Succeeded, $event->payment?->kind);
        $this->assertSame('9988112233', $event->payment?->paymentReference);

        // Verification fails with invalid hash
        $this->expectException(InvalidWebhook::class);
        $params['hash'] = 'invalid_tampered_hash';
        $this->gateway->verifyWebhook(json_encode($params), []);
    }

    public function test_refund_submits_cancel_refund_transaction_command(): void
    {
        $this->settings->save([
            'enabled' => true,
            'merchant_key' => 'MY_KEY',
            'test_mode' => true,
            'salt' => 'MY_SALT',
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => PayuGateway::CODE,
            'provider' => PayuGateway::CODE,
            'provider_reference' => 'PAYU_TXN_777',
            'provider_payment_id' => '403993715537142566',
            'amount' => '500.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PAID,
        ]);

        Http::fake([
            'https://test.payu.in/merchant/postservice.php*' => Http::response([
                'status' => 1,
                'msg' => 'Refund Request Accepted',
                'request_id' => 'REF_REQ_12345',
            ], 200),
        ]);

        $refundUpdate = $this->gateway->refund(
            $payment,
            Money::of('150.00', 'INR'),
            'idemp-payu-ref-123'
        );

        $this->assertSame(RefundUpdate::SUCCEEDED, $refundUpdate->status);
        $this->assertSame('REF_REQ_12345', $refundUpdate->reference);
        $this->assertSame('403993715537142566', $refundUpdate->paymentReference);
        $this->assertSame('150.00', $refundUpdate->amount->toDisplayDecimal());
    }
}
