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
use App\Domains\Pricing\Money;
use EcoModular\AmazonPaymentServices\AmazonPaymentServicesAmount;
use EcoModular\AmazonPaymentServices\AmazonPaymentServicesGateway;
use EcoModular\AmazonPaymentServices\AmazonPaymentServicesSettings;
use EcoModular\AmazonPaymentServices\AmazonPaymentServicesSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AmazonPaymentServicesTest extends TestCase
{
    use RefreshDatabase;

    private AmazonPaymentServicesSettings $settings;

    private AmazonPaymentServicesGateway $gateway;

    private AmazonPaymentServicesSignature $signature;

    private AmazonPaymentServicesAmount $amount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/amazon-payment-services/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        $loaded = app(ModuleLoader::class)->load('modrixcommerce/amazon-payment-services', 'modrixcommerce/amazon-payment-services', $manifest);
        $this->assertTrue($loaded, 'Module failed to load: ' . (app(ModuleLoader::class)->failure('modrixcommerce/amazon-payment-services')?->getMessage() ?? 'unknown error'));

        $this->settings = app(AmazonPaymentServicesSettings::class);
        $this->gateway = app(AmazonPaymentServicesGateway::class);
        $this->signature = app(AmazonPaymentServicesSignature::class);
        $this->amount = app(AmazonPaymentServicesAmount::class);
    }

    public function test_amount_conversions_respect_currency_minor_units(): void
    {
        // 2 decimals (AED, USD, SAR)
        $aed = Money::of('100.50', 'AED');
        $this->assertSame(10050, $this->amount->toMinor($aed));
        $this->assertSame('100.50', $this->amount->fromMinor(10050, 'AED')->toDisplayDecimal());

        // 3 decimals (KWD, BHD, OMR)
        $kwd = Money::of('25.750', 'KWD');
        $this->assertSame(25750, $this->amount->toMinor($kwd));
        $this->assertSame('25.750', $this->amount->fromMinor(25750, 'KWD')->toDisplayDecimal());

        // 0 decimals (JPY)
        $jpy = Money::of('5000', 'JPY');
        $this->assertSame(5000, $this->amount->toMinor($jpy));
        $this->assertSame('5000', $this->amount->fromMinor(5000, 'JPY')->toDisplayDecimal());
    }

    public function test_signature_calculation_and_verification(): void
    {
        $phrase = 'TEST_PASSPHRASE_123';
        $params = [
            'command' => 'PURCHASE',
            'access_code' => 'ACC123',
            'merchant_identifier' => 'M123',
            'amount' => '1050',
            'currency' => 'AED',
            'language' => 'en',
            'merchant_reference' => 'ORDER-101',
        ];

        $calculated = $this->signature->calculate($params, $phrase, 'sha256');
        $this->assertNotEmpty($calculated);

        $this->assertTrue($this->signature->verify($params, $calculated, $phrase, 'sha256'));
        $this->assertFalse($this->signature->verify($params, 'INVALID_SIGNATURE', $phrase, 'sha256'));
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(AmazonPaymentServicesGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('amazon-payment-services', $found->code());
        $this->assertSame('amazon-payment-services', $found->provider());
    }

    public function test_settings_update_encrypts_secrets_and_configures_gateway(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->update(
            enabled: true,
            merchantIdentifier: 'MERCHANT_APS_01',
            testMode: true,
            shaType: 'sha256',
            command: 'PURCHASE',
            language: 'en',
            label: 'Pay with Amazon Payment Services',
            currencies: ['AED', 'SAR', 'USD'],
            accessCode: 'SECURE_ACCESS_CODE',
            shaRequestPhrase: 'REQ_SECRET_PHRASE',
            shaResponsePhrase: 'RESP_SECRET_PHRASE',
        );

        $this->assertTrue($this->settings->enabled());
        $this->assertSame('MERCHANT_APS_01', $this->settings->merchantIdentifier());
        $this->assertSame('SECURE_ACCESS_CODE', $this->settings->accessCode());
        $this->assertSame('REQ_SECRET_PHRASE', $this->settings->shaRequestPhrase());
        $this->assertSame('RESP_SECRET_PHRASE', $this->settings->shaResponsePhrase());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertSame('••••••••', $array['access_code_hint']);
        $this->assertSame('••••••••', $array['sha_request_phrase_hint']);
        $this->assertSame('••••••••', $array['sha_response_phrase_hint']);
    }

    public function test_session_creation_generates_handoff_and_cache(): void
    {
        $this->settings->update(
            enabled: true,
            merchantIdentifier: 'MERCHANT_APS_01',
            testMode: true,
            shaType: 'sha256',
            command: 'PURCHASE',
            language: 'en',
            label: 'Amazon Payment Services',
            currencies: ['AED'],
            accessCode: 'ACC_CODE',
            shaRequestPhrase: 'REQ_PHRASE',
            shaResponsePhrase: 'RESP_PHRASE',
        );

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-1001',
            'email' => 'customer@example.ae',
            'currency' => 'AED',
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
            'payment_method_code' => AmazonPaymentServicesGateway::CODE,
            'payment_method_name' => 'Amazon Payment Services',
            'placed_at' => now(),
        ])->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => AmazonPaymentServicesGateway::CODE,
            'provider' => AmazonPaymentServicesGateway::CODE,
            'amount' => '250.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertStringStartsWith('APS-' . $payment->id, $session->reference);
        $this->assertStringContainsString('/m/modrixcommerce/amazon-payment-services/pay/' . $payment->id, $session->redirectUrl);

        $cached = Cache::get("aps:handoff:{$payment->id}");
        $this->assertIsArray($cached);
        $this->assertSame('25000', $cached['fields']['amount']);
        $this->assertSame('AED', $cached['fields']['currency']);
        $this->assertSame('PURCHASE', $cached['fields']['command']);
        $this->assertNotEmpty($cached['fields']['signature']);
    }

    public function test_webhook_verification_and_normalization(): void
    {
        $this->settings->update(
            enabled: true,
            merchantIdentifier: 'MERCHANT_APS_01',
            testMode: true,
            shaType: 'sha256',
            command: 'PURCHASE',
            language: 'en',
            label: 'Amazon Payment Services',
            currencies: ['AED'],
            accessCode: 'ACC_CODE',
            shaRequestPhrase: 'REQ_PHRASE',
            shaResponsePhrase: 'RESP_PHRASE',
        );

        $eventData = [
            'command' => 'PURCHASE',
            'response_code' => '14000',
            'response_message' => 'Success',
            'merchant_reference' => 'APS-99-REF',
            'fort_id' => '149999999999',
            'amount' => '15000',
            'currency' => 'AED',
        ];

        $eventData['signature'] = $this->signature->calculate($eventData, 'RESP_PHRASE', 'sha256');

        $verified = $this->gateway->verifyWebhook(json_encode($eventData), []);
        $this->assertSame('14000', $verified['response_code']);

        $normalized = $this->gateway->normalizeEvent($verified);
        $this->assertTrue($normalized->isRelevant());
        $this->assertNotNull($normalized->payment);
        $this->assertSame(PaymentUpdateKind::Succeeded, $normalized->payment->kind);
        $this->assertSame('149999999999', $normalized->payment->paymentReference);
        $this->assertSame('150.00', $normalized->payment->amount?->toDisplayDecimal());
    }

    public function test_webhook_throws_on_tampered_signature(): void
    {
        $this->settings->update(
            enabled: true,
            merchantIdentifier: 'M1',
            testMode: true,
            shaType: 'sha256',
            command: 'PURCHASE',
            language: 'en',
            label: 'APS',
            currencies: ['AED'],
            accessCode: 'AC',
            shaRequestPhrase: 'REQ',
            shaResponsePhrase: 'RESP',
        );

        $payload = json_encode([
            'response_code' => '14000',
            'merchant_reference' => 'APS-1',
            'signature' => 'FORGED_SIGNATURE',
        ]);

        $this->expectException(InvalidWebhook::class);
        $this->gateway->verifyWebhook($payload, []);
    }
}
