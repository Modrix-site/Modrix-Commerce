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
use EcoModular\MyFatoorah\MyFatoorahGateway;
use EcoModular\MyFatoorah\MyFatoorahSettings;
use EcoModular\MyFatoorah\MyFatoorahSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MyFatoorahTest extends TestCase
{
    use RefreshDatabase;

    private MyFatoorahSettings $settings;

    private MyFatoorahGateway $gateway;

    private MyFatoorahSignature $signature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/myfatoorah/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        app(ModuleLoader::class)->load('modrixcommerce/myfatoorah', 'modrixcommerce/myfatoorah', $manifest);

        $this->settings = app(MyFatoorahSettings::class);
        $this->gateway = app(MyFatoorahGateway::class);
        $this->signature = app(MyFatoorahSignature::class);
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(MyFatoorahGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('myfatoorah', $found->code());
        $this->assertSame('myfatoorah', $found->provider());
    }

    public function test_determines_availability_based_on_currency_and_configuration(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->save([
            'enabled' => true,
            'test_mode' => true,
            'label' => 'MyFatoorah UAE',
            'currencies' => ['AED', 'SAR', 'KWD'],
            'api_key' => 'TEST_TOKEN_SECRET_12345',
            'webhook_secret' => 'TEST_WEBHOOK_SECRET_98765',
        ]);

        $this->assertSame('TEST_TOKEN_SECRET_12345', $this->settings->apiKey());
        $this->assertSame('TEST_WEBHOOK_SECRET_98765', $this->settings->webhookSecret());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertTrue($array['has_api_key']);
        $this->assertTrue($array['has_webhook_secret']);
        $this->assertArrayNotHasKey('api_key', $array);

        $aedCart = new PricedCart('AED', []);
        $aedCart->shippingTotal = Money::of('500.00', 'AED');

        $inrCart = new PricedCart('INR', []);
        $inrCart->shippingTotal = Money::of('500.00', 'INR');

        $this->assertTrue($this->gateway->isAvailable($aedCart));
        $this->assertFalse($this->gateway->isAvailable($inrCart));
    }

    public function test_webhook_signature_calculation_and_verification(): void
    {
        $payload = json_encode(['Event' => 1, 'Data' => ['InvoiceId' => 123456, 'InvoiceStatus' => 'Paid']]);
        $secret = 'super_secret_webhook_key_xyz';

        $signature = $this->signature->generateSignature($payload, $secret);

        $this->assertTrue($this->signature->verifyWebhookSignature($payload, $signature, $secret));
        $this->assertFalse($this->signature->verifyWebhookSignature($payload, 'invalid_sig', $secret));
        $this->assertFalse($this->signature->verifyWebhookSignature($payload, $signature, 'wrong_secret'));
    }

    public function test_session_creation_calls_execute_payment_and_returns_checkout_session(): void
    {
        $this->settings->save([
            'enabled' => true,
            'api_key' => 'MY_API_KEY',
            'test_mode' => true,
            'label' => 'MyFatoorah',
            'currencies' => ['AED'],
        ]);

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-MF-101',
            'email' => 'customer@myfatoorah.ae',
            'phone' => '0501234567',
            'currency' => 'AED',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'subtotal' => '300.00',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '300.00',
            'paid_total' => '0.00',
            'refunded_total' => '0.00',
            'payment_method_code' => MyFatoorahGateway::CODE,
            'payment_method_name' => 'MyFatoorah',
            'placed_at' => now(),
        ]);
        $order->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => MyFatoorahGateway::CODE,
            'provider' => MyFatoorahGateway::CODE,
            'amount' => '300.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        Http::fake([
            'https://apitest.myfatoorah.com/v2/ExecutePayment*' => Http::response([
                'IsSuccess' => true,
                'Message' => '',
                'Data' => [
                    'InvoiceId' => 889900,
                    'PaymentURL' => 'https://portaltest.myfatoorah.com/Payment/Invoice/889900',
                    'CustomerReference' => 'MF_' . $payment->id,
                ],
            ], 200),
        ]);

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertStringStartsWith('MF_' . $payment->id, $session->reference);
        $this->assertSame('https://portaltest.myfatoorah.com/Payment/Invoice/889900', $session->redirectUrl);

        $cachedHandoff = Cache::get("myfatoorah:handoff:{$payment->id}");
        $this->assertIsArray($cachedHandoff);
        $this->assertSame('https://portaltest.myfatoorah.com/Payment/Invoice/889900', $cachedHandoff['checkoutUrl']);

        $cachedSession = Cache::get("myfatoorah:session:{$session->reference}");
        $this->assertIsArray($cachedSession);
        $this->assertSame('889900', $cachedSession['invoiceId']);
        $this->assertSame('AED', $cachedSession['currency']);
    }

    public function test_retrieve_queries_myfatoorah_api_and_normalizes_payment(): void
    {
        $this->settings->save([
            'enabled' => true,
            'api_key' => 'MY_API_KEY',
            'test_mode' => true,
        ]);

        $customerRef = 'MF_TXN_555';

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => MyFatoorahGateway::CODE,
            'provider' => MyFatoorahGateway::CODE,
            'provider_reference' => $customerRef,
            'amount' => '400.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PENDING,
        ]);

        Cache::put("myfatoorah:session:{$customerRef}", [
            'invoiceId' => '991122',
        ], 30);

        Http::fake([
            'https://apitest.myfatoorah.com/v2/GetPaymentStatus*' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'InvoiceId' => 991122,
                    'InvoiceStatus' => 'Paid',
                    'InvoiceValue' => 400.00,
                    'CustomerReference' => $customerRef,
                    'InvoiceTransactions' => [
                        [
                            'TransactionId' => '070712345678',
                            'PaymentId' => '1002026110712345',
                            'TransactionStatus' => 'Succss',
                            'TransactionValue' => '400.00',
                            'PaidCurrency' => 'AED',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $update = $this->gateway->retrieve($payment);

        $this->assertSame(PaymentUpdateKind::Succeeded, $update->kind);
        $this->assertSame($customerRef, $update->sessionReference);
        $this->assertSame('1002026110712345', $update->paymentReference);
        $this->assertNotNull($update->amount);
        $this->assertSame('400.00', $update->amount->toDisplayDecimal());
    }

    public function test_webhook_verification_and_event_normalization(): void
    {
        $secret = 'webhook_sec_abc123';
        $this->settings->save([
            'enabled' => true,
            'api_key' => 'MY_API_KEY',
            'webhook_secret' => $secret,
        ]);

        $payloadData = [
            'Event' => 1,
            'Data' => [
                'InvoiceId' => 991122,
                'PaymentId' => '1002026110799999',
                'InvoiceStatus' => 'Paid',
                'CustomerReference' => 'MF_TXN_888',
                'InvoiceValue' => 500.00,
                'DisplayCurrencyIso' => 'AED',
            ],
        ];

        $payload = json_encode($payloadData);
        $signature = $this->signature->generateSignature($payload, $secret);

        $headers = [
            'myfatoorah-signature' => [$signature],
        ];

        $verified = $this->gateway->verifyWebhook($payload, $headers);
        $this->assertSame(1, $verified['Event']);

        $event = $this->gateway->normalizeEvent($verified);
        $this->assertSame('MF_TXN_888', $event->id);
        $this->assertSame(PaymentUpdateKind::Succeeded, $event->payment?->kind);
        $this->assertSame('1002026110799999', $event->payment?->paymentReference);

        // Verification fails with invalid signature
        $this->expectException(InvalidWebhook::class);
        $invalidHeaders = ['myfatoorah-signature' => ['invalid_tampered_sig']];
        $this->gateway->verifyWebhook($payload, $invalidHeaders);
    }

    public function test_refund_submits_myfatoorah_api_request(): void
    {
        $this->settings->save([
            'enabled' => true,
            'api_key' => 'MY_API_KEY',
            'test_mode' => true,
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => MyFatoorahGateway::CODE,
            'provider' => MyFatoorahGateway::CODE,
            'provider_reference' => 'MF_TXN_777',
            'provider_payment_id' => '1002026110777777',
            'amount' => '500.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PAID,
        ]);

        Http::fake([
            'https://apitest.myfatoorah.com/v2/MakeRefund*' => Http::response([
                'IsSuccess' => true,
                'Message' => 'Refund created successfully',
                'Data' => [
                    'RefundId' => 123456,
                    'RefundStatus' => 'Success',
                ],
            ], 200),
        ]);

        $refundUpdate = $this->gateway->refund(
            $payment,
            Money::of('150.00', 'AED'),
            'idemp-mf-ref-123'
        );

        $this->assertSame(RefundUpdate::SUCCEEDED, $refundUpdate->status);
        $this->assertSame('123456', $refundUpdate->reference);
        $this->assertSame('1002026110777777', $refundUpdate->paymentReference);
        $this->assertSame('150.00', $refundUpdate->amount->toDisplayDecimal());
    }
}
