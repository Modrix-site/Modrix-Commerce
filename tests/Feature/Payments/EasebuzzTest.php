<?php

namespace Tests\Feature\Payments;

use App\Core\Extensions\Manifest;
use App\Core\Extensions\ModuleLoader;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Contracts\PaymentGateway;
use App\Domains\Payments\Gateway\InvalidWebhook;
use App\Domains\Payments\Gateway\PaymentUpdateKind;
use App\Domains\Payments\Gateway\RefundUpdate;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Pricing\Money;
use EcoModular\Easebuzz\EasebuzzGateway;
use EcoModular\Easebuzz\EasebuzzSettings;
use EcoModular\Easebuzz\EasebuzzSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EasebuzzTest extends TestCase
{
    use RefreshDatabase;

    private EasebuzzSettings $settings;

    private EasebuzzGateway $gateway;

    private EasebuzzSignature $signature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/easebuzz/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        app(ModuleLoader::class)->load('modrixcommerce/easebuzz', 'modrixcommerce/easebuzz', $manifest);

        $this->settings = app(EasebuzzSettings::class);
        $this->gateway = app(EasebuzzGateway::class);
        $this->signature = app(EasebuzzSignature::class);
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(EasebuzzGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('easebuzz', $found->code());
        $this->assertSame('easebuzz', $found->provider());
    }

    public function test_sha512_signature_calculation_and_reverse_verification(): void
    {
        $key = '2PBP7IABZ2';
        $salt = 'DAH88E3UWQ';
        $txnid = 'TXN1001';
        $amount = '10.00';
        $productinfo = 'Test Product';
        $firstname = 'John';
        $email = 'john@example.com';
        $phone = '9999999999';

        // 1. Request Hash (10 empty UDFs between email and salt = 11 pipes)
        $expectedSeq = "$key|$txnid|$amount|$productinfo|$firstname|$email|||||||||||$salt";
        $expectedHash = strtolower(hash('sha512', $expectedSeq));

        $hash = $this->signature->generateInitiateHash(
            $key,
            $txnid,
            $amount,
            $productinfo,
            $firstname,
            $email,
            $salt
        );

        $this->assertSame($expectedHash, $hash);

        // 2. Reverse Response Hash (10 empty UDFs between status and email = 11 pipes)
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
            'udf6' => '',
            'udf7' => '',
            'udf8' => '',
            'udf9' => '',
            'udf10' => '',
        ];

        $reverseSeq = "$salt|success|||||||||||$email|$firstname|$productinfo|$amount|$txnid|$key";
        $responseHash = strtolower(hash('sha512', $reverseSeq));
        $responseParams['hash'] = $responseHash;

        $this->assertTrue($this->signature->verifyResponseHash($responseParams, $salt));
        $this->assertFalse($this->signature->verifyResponseHash($responseParams, 'wrong_salt'));

        // 3. Retrieve Hash
        $retrieveSeq = "$key|$txnid|$amount|$email|$phone|$salt";
        $expectedRetrieveHash = strtolower(hash('sha512', $retrieveSeq));
        $this->assertSame($expectedRetrieveHash, $this->signature->generateRetrieveHash($key, $txnid, $amount, $email, $phone, $salt));

        // 4. Refund Hash
        $refundAmount = '5.00';
        $refundSeq = "$key|$txnid|$phone|$email|$amount|$refundAmount|$salt";
        $expectedRefundHash = strtolower(hash('sha512', $refundSeq));
        $this->assertSame($expectedRefundHash, $this->signature->generateRefundHash($key, $txnid, $phone, $email, $amount, $refundAmount, $salt));
    }

    public function test_determines_availability_based_on_currency_and_configuration(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->save([
            'enabled' => true,
            'merchant_key' => 'TEST_KEY_123',
            'test_mode' => true,
            'label' => 'Easebuzz India',
            'currencies' => ['INR'],
            'salt' => 'HIGHLY_SECRET_SALT_999',
        ]);

        $this->assertSame('TEST_KEY_123', $this->settings->merchantKey());
        $this->assertSame('HIGHLY_SECRET_SALT_999', $this->settings->salt());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertTrue($array['has_salt']);
        $this->assertArrayNotHasKey('salt', $array);

        $inrCart = new PricedCart('INR', []);
        $inrCart->shippingTotal = Money::of('500.00', 'INR');

        $usdCart = new PricedCart('USD', []);
        $usdCart->shippingTotal = Money::of('500.00', 'USD');

        $this->assertTrue($this->gateway->isAvailable($inrCart));
        $this->assertFalse($this->gateway->isAvailable($usdCart));
    }

    public function test_session_creation_calls_initiate_api_and_returns_checkout_session(): void
    {
        $this->settings->save([
            'enabled' => true,
            'merchant_key' => 'MY_KEY',
            'test_mode' => true,
            'label' => 'Easebuzz',
            'currencies' => ['INR'],
            'salt' => 'MY_SALT',
        ]);

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-EB-101',
            'email' => 'customer@easebuzz.in',
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
            'payment_method_code' => EasebuzzGateway::CODE,
            'payment_method_name' => 'Easebuzz',
            'placed_at' => now(),
        ]);
        $order->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => EasebuzzGateway::CODE,
            'provider' => EasebuzzGateway::CODE,
            'amount' => '250.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        Http::fake([
            'https://testpay.easebuzz.in/payment/initiateLink*' => Http::response([
                'status' => 1,
                'data' => '44a56c703b44b6e5e8942b109dcba801a',
            ], 200),
        ]);

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertStringStartsWith('EB_'.$payment->id, $session->reference);
        $this->assertSame('https://testpay.easebuzz.in/pay/44a56c703b44b6e5e8942b109dcba801a', $session->redirectUrl);

        $cachedHandoff = Cache::get("easebuzz:handoff:{$payment->id}");
        $this->assertIsArray($cachedHandoff);
        $this->assertSame('https://testpay.easebuzz.in/pay/44a56c703b44b6e5e8942b109dcba801a', $cachedHandoff['checkoutUrl']);

        $cachedSession = Cache::get("easebuzz:session:{$session->reference}");
        $this->assertIsArray($cachedSession);
        $this->assertSame('250.00', $cachedSession['amount']);
        $this->assertSame('customer@easebuzz.in', $cachedSession['email']);
    }

    public function test_retrieve_queries_easebuzz_api_and_normalizes_payment(): void
    {
        $this->settings->save([
            'enabled' => true,
            'merchant_key' => 'MY_KEY',
            'test_mode' => true,
            'salt' => 'MY_SALT',
        ]);

        $txnid = 'EB_TXN_555';

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => EasebuzzGateway::CODE,
            'provider' => EasebuzzGateway::CODE,
            'provider_reference' => $txnid,
            'amount' => '400.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake([
            'https://testdashboard.easebuzz.in/transaction/v1/retrieve*' => Http::response([
                'status' => true,
                'msg' => [
                    'txnid' => $txnid,
                    'easepayid' => 'EASE_999888',
                    'status' => 'success',
                    'amount' => '400.00',
                ],
            ], 200),
        ]);

        $update = $this->gateway->retrieve($payment);

        $this->assertSame(PaymentUpdateKind::Succeeded, $update->kind);
        $this->assertSame($txnid, $update->sessionReference);
        $this->assertSame('EASE_999888', $update->paymentReference);
        $this->assertNotNull($update->amount);
        $this->assertSame('400.00', $update->amount->toDisplayDecimal());
    }

    public function test_webhook_verification_and_event_normalization(): void
    {
        $key = 'MY_KEY';
        $salt = 'MY_SALT';
        $txnid = 'EB_TXN_888';

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
            'easepayid' => 'EASE_887766',
            'udf1' => '',
            'udf2' => '',
            'udf3' => '',
            'udf4' => '',
            'udf5' => '',
            'udf6' => '',
            'udf7' => '',
            'udf8' => '',
            'udf9' => '',
            'udf10' => '',
        ];

        $reverseSeq = "$salt|success|||||||||||alice@test.in|Alice|Order 101|500.00|$txnid|$key";
        $params['hash'] = strtolower(hash('sha512', $reverseSeq));

        $payload = json_encode($params);

        $verified = $this->gateway->verifyWebhook($payload, []);
        $this->assertSame($txnid, $verified['txnid']);

        $event = $this->gateway->normalizeEvent($verified);
        $this->assertSame($txnid, $event->id);
        $this->assertSame(PaymentUpdateKind::Succeeded, $event->payment?->kind);
        $this->assertSame('EASE_887766', $event->payment?->paymentReference);

        // Verification fails with invalid hash
        $this->expectException(InvalidWebhook::class);
        $params['hash'] = 'invalid_tampered_hash';
        $this->gateway->verifyWebhook(json_encode($params), []);
    }

    public function test_refund_submits_easebuzz_api_request(): void
    {
        $this->settings->save([
            'enabled' => true,
            'merchant_key' => 'MY_KEY',
            'test_mode' => true,
            'salt' => 'MY_SALT',
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => EasebuzzGateway::CODE,
            'provider' => EasebuzzGateway::CODE,
            'provider_reference' => 'EB_TXN_777',
            'provider_payment_id' => 'EASE_999888',
            'amount' => '500.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PAID,
        ]);

        Http::fake([
            'https://testdashboard.easebuzz.in/transaction/v1/refund*' => Http::response([
                'status' => true,
                'msg' => 'Refund Initiated Successfully',
                'refund_id' => 'REF_EB_12345',
            ], 200),
        ]);

        $refundUpdate = $this->gateway->refund(
            $payment,
            Money::of('150.00', 'INR'),
            'idemp-eb-ref-123'
        );

        $this->assertSame(RefundUpdate::SUCCEEDED, $refundUpdate->status);
        $this->assertSame('REF_EB_12345', $refundUpdate->reference);
        $this->assertSame('EASE_999888', $refundUpdate->paymentReference);
        $this->assertSame('150.00', $refundUpdate->amount->toDisplayDecimal());
    }
}
