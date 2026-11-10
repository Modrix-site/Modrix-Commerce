<?php

namespace Tests\Feature\Payments;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Gateway\RefundUpdate;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Models\PaymentWebhookEvent;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Storefront\CheckoutController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use ModrixCommerce\CCAvenue\CCAvenueCrypto;
use ModrixCommerce\CCAvenue\CCAvenueGateway;
use ModrixCommerce\CCAvenue\CCAvenueSettings;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class CCAvenueTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        $this->enableCheckout();
        $this->setUpModules();
        config(['inertia.ssr.enabled' => false]);
        Mail::fake();
        Http::preventStrayRequests();
        File::copyDirectory(base_path('modules/modrixcommerce/ccavenue'), $this->modulesPath.'/modrixcommerce/ccavenue');
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('modrixcommerce/ccavenue');
        app(ModuleManager::class)->enable('modrixcommerce/ccavenue', true);
    }

    private function configure(): void
    {
        app(CCAvenueSettings::class)->update([
            'enabled' => true, 'merchant_id' => '123456', 'access_code' => 'accesscode1234',
            'working_key' => 'workingkey123456789', 'currencies' => ['USD'],
        ]);
    }

    private function checkout(): Payment
    {
        $this->configure();
        $this->addToCart($this->sellable('20.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'ccavenue']))->assertSessionHasNoErrors()->assertRedirect();

        return Payment::query()->where('provider', 'ccavenue')->firstOrFail();
    }

    private function fakeStatus(Payment $payment, array $overrides = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $body = array_replace([
            'status' => 0, 'order_no' => $payment->provider_reference, 'reference_no' => '12345678901',
            'order_status' => 'Shipped', 'order_amt' => '25.00', 'order_currncy' => 'USD',
        ], $overrides);
        Http::fake(['apitest.ccavenue.com/*' => Http::response(http_build_query([
            'status' => '0', 'enc_response' => CCAvenueCrypto::encrypt(json_encode($body), 'workingkey123456789'),
        ]))]);
    }

    private function notification(Payment $payment, array $overrides = []): array
    {
        return ['encResp' => CCAvenueCrypto::encrypt(http_build_query(array_replace([
            'order_id' => $payment->provider_reference, 'order_status' => 'Success', 'billing_email' => 'private@example.com',
        ], $overrides)), 'workingkey123456789')];
    }

    private function postForm(string $url, array $fields)
    {
        return $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($fields));
    }

    public function test_lifecycle_registers_gateway_and_settings_control_checkout(): void
    {
        $methods = app(PaymentMethods::class);
        $this->assertNull($methods->find('ccavenue'));
        $this->configure();
        $this->assertInstanceOf(CCAvenueGateway::class, $methods->find('ccavenue'));
        app(CCAvenueSettings::class)->update(['enabled' => false]);
        $this->assertNull($methods->find('ccavenue'));
        $this->assertInstanceOf(CCAvenueGateway::class, $methods->gatewayForProvider('ccavenue'));
    }

    public function test_handoff_requires_ownership_and_contains_encrypted_server_amount(): void
    {
        $payment = $this->checkout();
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->payment_status);
        $this->get($payment->checkout_url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page->component('module/modrixcommerce/ccavenue/storefront/handoff', false)->where('action', 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction'));
        $handoff = Cache::get('ccavenue:handoff:'.$payment->id);
        parse_str(CCAvenueCrypto::decrypt($handoff['encRequest'], 'workingkey123456789'), $fields);
        $this->assertSame('25.00', $fields['amount']);
        $this->assertSame($payment->provider_reference, $fields['order_id']);
        $this->assertLessThanOrEqual(30, strlen($fields['order_id']));
        $this->assertMatchesRegularExpression('/^[0-9]{17}$/D', $fields['tid']);
        $this->assertArrayNotHasKey('delivery_address', $fields);
        $this->withSession([CheckoutController::SESSION_ORDERS => []])->get($payment->checkout_url)->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_duplicate_callback_verifies_status_and_runs_core_order_workflow_once(): void
    {
        $payment = $this->checkout();
        $this->fakeStatus($payment);
        $notification = $this->notification($payment);
        $this->postForm(route('modrixcommerce.ccavenue.response'), $notification)->assertStatus(303);
        $this->postForm(route('modrixcommerce.ccavenue.response'), $notification)->assertStatus(303);
        $this->assertSame(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
        $this->assertSame(1, $payment->order->invoice()->count());
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertStringNotContainsString('private@example.com', json_encode(PaymentWebhookEvent::query()->first()->payload));
        Http::assertSentCount(1);
    }

    public function test_callback_cannot_override_server_status_and_amount_mismatch_is_held(): void
    {
        $payment = $this->checkout();
        $this->fakeStatus($payment, ['order_status' => 'Initiated']);
        $this->postForm(route('webhooks.payments', 'ccavenue'), $this->notification($payment))->assertOk();
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
        $this->fakeStatus($payment, ['order_amt' => '0.01']);
        $this->get(route('checkout.payment.return', $payment->order))->assertRedirect();
        $this->assertSame(Payment::STATUS_MISMATCH, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
    }

    public function test_malformed_and_disabled_callbacks_are_rejected(): void
    {
        $this->configure();
        $this->postForm(route('modrixcommerce.ccavenue.response'), ['encResp' => 'invalid'])->assertStatus(400);
        $this->postForm(route('webhooks.payments', 'ccavenue'), ['order_status' => 'Success'])->assertStatus(400);
        app(CCAvenueSettings::class)->update(['callbacks_enabled' => false]);
        $this->postForm(route('webhooks.payments', 'ccavenue'), ['encResp' => str_repeat('ab', 16)])->assertStatus(400);
        $this->assertSame(0, PaymentWebhookEvent::query()->count());
        Http::assertNothingSent();
    }

    public function test_admin_settings_mask_credentials_preserve_blank_secrets_and_require_permission(): void
    {
        $this->configure();
        $owner = $this->userWithRole('administrator');
        $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.modrixcommerce.ccavenue.index'))
            ->assertInertia(fn (Assert $page) => $page->where('settings.working_key_saved', true)->missing('settings.working_key')->missing('settings.access_code'));
        $data = array_intersect_key(app(CCAvenueSettings::class)->toArray(), CCAvenueSettings::DEFAULTS);
        $this->put(route('admin.modrixcommerce.ccavenue.update'), $data + ['access_code' => '', 'working_key' => ''])->assertSessionHasNoErrors();
        $this->assertSame('workingkey123456789', app(CCAvenueSettings::class)->secret('working_key'));
        $this->assertStringNotContainsString('workingkey123456789', app(SettingsRepository::class)->get('secrets.ccavenue.working_key'));
        $invalid = array_replace($data, ['label' => '', 'access_code' => 'newsecret1234', 'working_key' => 'privatekey123456']);
        $this->put(route('admin.modrixcommerce.ccavenue.update'), $invalid)
            ->assertSessionHasErrors('label')->assertSessionMissing('_old_input.access_code')->assertSessionMissing('_old_input.working_key');
        $this->actingAs($this->userWithRole('store-manager'))->put(route('admin.modrixcommerce.ccavenue.update'), $data)->assertForbidden();
    }

    public function test_refund_switch_and_stable_reference_are_sent_to_provider(): void
    {
        $payment = $this->checkout();
        $this->fakeStatus($payment);
        $this->get(route('checkout.payment.return', $payment->order));
        $payment->refresh();
        try {
            app(CCAvenueGateway::class)->refund($payment, Money::of('5', 'USD'), 'refund-1');
            $this->fail('Refunds must be explicitly enabled.');
        } catch (GatewayException $e) {
            $this->assertStringContainsString('disabled', $e->getMessage());
        }
        app(CCAvenueSettings::class)->update(['refunds_enabled' => true]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['apitest.ccavenue.com/*' => Http::response(http_build_query([
            'status' => '0', 'enc_response' => CCAvenueCrypto::encrypt('{"refund_status":0}', 'workingkey123456789'),
        ]))]);
        $update = app(CCAvenueGateway::class)->refund($payment, Money::of('5', 'USD'), 'refund-1');
        $this->assertSame(RefundUpdate::PENDING, $update->status);
        $this->assertSame(substr(hash('sha256', 'refund-1'), 0, 30), $update->reference);
        Http::assertSent(function ($request) {
            if ($request['command'] !== 'refundOrder') {
                return false;
            }
            $fields = json_decode(CCAvenueCrypto::decrypt($request['enc_request'], 'workingkey123456789'), true);

            return $fields['refund_amount'] === '5.00' && $fields['refund_ref_no'] === substr(hash('sha256', 'refund-1'), 0, 30);
        });
    }
}
