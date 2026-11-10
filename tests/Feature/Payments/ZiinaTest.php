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
use EcoModular\Ziina\ZiinaAmount;
use EcoModular\Ziina\ZiinaGateway;
use EcoModular\Ziina\ZiinaSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZiinaTest extends TestCase
{
    use RefreshDatabase;

    private ZiinaSettings $settings;

    private ZiinaGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        $manifestData = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/ziina/module.json')), true);
        $manifest = Manifest::fromArray($manifestData);
        app(ModuleLoader::class)->load('modrixcommerce/ziina', 'modrixcommerce/ziina', $manifest);

        $this->settings = app(ZiinaSettings::class);
        $this->gateway = app(ZiinaGateway::class);
    }

    public function test_gateway_registers_with_payment_methods(): void
    {
        $methods = app(PaymentMethods::class);
        $found = $methods->gateway(ZiinaGateway::CODE);

        $this->assertNotNull($found);
        $this->assertInstanceOf(PaymentGateway::class, $found);
        $this->assertSame('ziina', $found->code());
        $this->assertSame('ziina', $found->provider());
    }

    public function test_minor_units_conversion(): void
    {
        $aed = Money::of('199.50', 'AED');
        $this->assertSame(19950, ZiinaAmount::toMinorUnits($aed));

        $restored = ZiinaAmount::fromMinorUnits(19950, 'AED');
        $this->assertSame('199.50', $restored->toDisplayDecimal());
    }

    public function test_settings_save_and_encryption(): void
    {
        $this->assertFalse($this->gateway->isConfigured());

        $this->settings->save([
            'enabled' => true,
            'test_mode' => true,
            'label' => 'Ziina Apple Pay',
            'currencies' => ['AED', 'USD'],
            'api_key' => 'ZIINA_SECRET_BEARER_TOKEN_999',
            'webhook_secret' => 'ZIINA_WEBHOOK_SECRET_HMAC',
        ]);

        $this->assertTrue($this->settings->enabled());
        $this->assertSame('ZIINA_SECRET_BEARER_TOKEN_999', $this->settings->apiKey());
        $this->assertSame('ZIINA_WEBHOOK_SECRET_HMAC', $this->settings->webhookSecret());
        $this->assertTrue($this->gateway->isConfigured());

        $array = $this->settings->toArray();
        $this->assertTrue($array['has_api_key']);
        $this->assertTrue($array['has_webhook_secret']);
        $this->assertArrayNotHasKey('api_key', $array);
        $this->assertArrayNotHasKey('webhook_secret', $array);
    }

    public function test_session_creation_creates_payment_intent_and_returns_session(): void
    {
        $this->settings->save([
            'enabled' => true,
            'test_mode' => true,
            'api_key' => 'MOCK_ZIINA_KEY',
            'currencies' => ['AED'],
        ]);

        Http::fake([
            'https://api-v2.ziina.com/api/payment_intent' => Http::response([
                'id' => 'pi_mock_123456789',
                'amount' => 45000,
                'currency_code' => 'AED',
                'status' => 'requires_payment_instrument',
                'redirect_url' => 'https://pay.ziina.com/pi_mock_123456789',
            ], 201),
        ]);

        $order = new Order;
        $order->forceFill([
            'number' => 'ORD-ZIINA-101',
            'email' => 'buyer@ziinauser.ae',
            'phone' => '+971501112233',
            'currency' => 'AED',
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
            'payment_method_code' => ZiinaGateway::CODE,
            'payment_method_name' => 'Ziina',
            'placed_at' => now(),
        ]);
        $order->save();

        $payment = (new Payment)->forceFill([
            'order_id' => $order->id,
            'method_code' => ZiinaGateway::CODE,
            'provider' => ZiinaGateway::CODE,
            'amount' => '450.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PENDING,
        ]);
        $payment->save();

        $session = $this->gateway->createSession($order, $payment, 'https://example.com/return', 'https://example.com/cancel');

        $this->assertSame('pi_mock_123456789', $session->reference);
        $this->assertSame('https://pay.ziina.com/pi_mock_123456789', $session->redirectUrl);
    }

    public function test_retrieve_queries_payment_intent_and_normalizes_status(): void
    {
        $this->settings->save([
            'enabled' => true,
            'test_mode' => true,
            'api_key' => 'MOCK_ZIINA_KEY',
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => ZiinaGateway::CODE,
            'provider' => ZiinaGateway::CODE,
            'provider_reference' => 'pi_mock_999888',
            'amount' => '300.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake([
            'https://api-v2.ziina.com/api/payment_intent/pi_mock_999888' => Http::response([
                'id' => 'pi_mock_999888',
                'amount' => 30000,
                'currency_code' => 'AED',
                'status' => 'completed',
            ], 200),
        ]);

        $update = $this->gateway->retrieve($payment);

        $this->assertSame(PaymentUpdateKind::Succeeded, $update->kind);
        $this->assertSame('pi_mock_999888', $update->sessionReference);
        $this->assertSame('pi_mock_999888', $update->paymentReference);
        $this->assertNotNull($update->amount);
        $this->assertSame('300.00', $update->amount->toDisplayDecimal());
    }

    public function test_webhook_verification_and_event_normalization(): void
    {
        $secret = 'mock_webhook_secret_123';
        $this->settings->save([
            'enabled' => true,
            'api_key' => 'MOCK_KEY',
            'webhook_secret' => $secret,
        ]);

        $payload = json_encode([
            'event' => 'payment_intent.status.updated',
            'data' => [
                'id' => 'pi_mock_777',
                'amount' => 20000,
                'currency_code' => 'AED',
                'status' => 'completed',
            ],
        ]);

        $validSignature = hash_hmac('sha256', $payload, $secret);

        $verified = $this->gateway->verifyWebhook($payload, ['x-hmac-signature' => $validSignature]);
        $this->assertSame('payment_intent.status.updated', $verified['event']);

        $event = $this->gateway->normalizeEvent($verified);
        $this->assertSame('pi_mock_777', $event->id);
        $this->assertSame(PaymentUpdateKind::Succeeded, $event->payment?->kind);

        // Verification fails with tampered signature
        $this->expectException(InvalidWebhook::class);
        $this->gateway->verifyWebhook($payload, ['x-hmac-signature' => 'tampered_signature']);
    }

    public function test_refund_submits_to_ziina_and_returns_update(): void
    {
        $this->settings->save([
            'enabled' => true,
            'test_mode' => true,
            'api_key' => 'MOCK_KEY',
        ]);

        $payment = (new Payment)->forceFill([
            'order_id' => 1,
            'method_code' => ZiinaGateway::CODE,
            'provider' => ZiinaGateway::CODE,
            'provider_reference' => 'pi_original_555',
            'amount' => '600.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_PAID,
        ]);

        Http::fake([
            'https://api-v2.ziina.com/api/refund' => Http::response([
                'id' => 'ref_mock_999',
                'payment_intent_id' => 'pi_original_555',
                'amount' => 25000,
                'currency_code' => 'AED',
                'status' => 'completed',
            ], 200),
        ]);

        $refundUpdate = $this->gateway->refund(
            $payment,
            Money::of('250.00', 'AED'),
            'idemp-ziina-ref-123'
        );

        $this->assertSame(RefundUpdate::SUCCEEDED, $refundUpdate->status);
        $this->assertSame('ref_mock_999', $refundUpdate->reference);
        $this->assertSame('pi_original_555', $refundUpdate->paymentReference);
        $this->assertSame('250.00', $refundUpdate->amount->toDisplayDecimal());
    }
}
