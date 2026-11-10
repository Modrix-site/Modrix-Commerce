<?php

namespace Tests\Feature\Payments;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Refund;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Models\PaymentWebhookEvent;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Payments\PaymentReconciler;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Storefront\CheckoutController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use ModrixCommerce\PayTabs\PayTabsGateway;
use ModrixCommerce\PayTabs\PayTabsSettings;
use ModrixCommerce\PayTabs\PayTabsSignature;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class PayTabsTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const KEY = 'test-server-key-123456789';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        $this->enableCheckout();
        $this->setUpModules();
        config(['inertia.ssr.enabled' => false]);
        URL::forceScheme('https');
        Mail::fake();
        Http::preventStrayRequests();
        File::copyDirectory(base_path('modules/modrixcommerce/paytabs'), $this->modulesPath.'/modrixcommerce/paytabs');
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('modrixcommerce/paytabs');
        app(ModuleManager::class)->enable('modrixcommerce/paytabs', true);
    }

    private function configure(array $overrides = []): void
    {
        app(PayTabsSettings::class)->update(array_replace([
            'enabled' => true, 'test_profile_id' => '12345', 'test_server_key' => self::KEY,
            'profile_id' => '67890', 'server_key' => 'live-server-key-123456789', 'currencies' => ['USD'],
        ], $overrides));
    }

    private function fakeApi(?callable $custom = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($custom) {
            if ($custom !== null && ($response = $custom($request)) !== null) {
                return $response;
            }
            if (($request->data()['tran_type'] ?? null) === 'sale') {
                $host = 'https://'.parse_url($request->url(), PHP_URL_HOST);

                return Http::response(['tran_ref' => 'TSTPAY000000001', 'profileId' => $request['profile_id'], 'cart_id' => $request['cart_id'], 'redirect_url' => $host.'/payment/page/123456789']);
            }
            $payment = Payment::query()->where('provider', 'paytabs')->firstOrFail();

            return Http::response($this->transaction($payment));
        });
    }

    private function checkout(array $settings = []): Payment
    {
        $this->configure($settings);
        $this->fakeApi();
        $this->addToCart($this->sellable('20.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'paytabs']))->assertSessionHasNoErrors()->assertRedirect('https://'.parse_url(PayTabsSettings::REGIONS[app(PayTabsSettings::class)->value('region')][1], PHP_URL_HOST).'/payment/page/123456789');

        return Payment::query()->where('provider', 'paytabs')->firstOrFail();
    }

    private function transaction(Payment $payment, array $overrides = []): array
    {
        return array_replace_recursive([
            'tran_ref' => $payment->provider_reference, 'profileId' => 12345, 'tran_type' => 'Sale',
            'cart_id' => app(PayTabsGateway::class)->context($payment)->cart_id,
            'cart_amount' => '25.00', 'cart_currency' => 'USD', 'payment_result' => ['response_status' => 'A'],
            'customer_details' => ['email' => 'private@example.com'],
        ], $overrides);
    }

    private function postCallback(array $data, ?string $signature = null)
    {
        $payload = json_encode($data);

        return $this->call('POST', route('webhooks.payments', 'paytabs'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_SIGNATURE' => $signature ?? hash_hmac('sha256', $payload, self::KEY)], $payload);
    }

    private function paid(): Payment
    {
        $payment = $this->checkout();
        app(PaymentReconciler::class)->reconcile($payment);

        return $payment->refresh();
    }

    private function refundData(Payment $payment, string $status = 'A'): array
    {
        return $this->transaction($payment, ['tran_ref' => 'TSTREFUND000001', 'previous_tran_ref' => $payment->provider_payment_id, 'tran_type' => 'Refund', 'cart_amount' => '5.00', 'payment_result' => ['response_status' => $status]]);
    }

    public function test_lifecycle_and_settings_gate_checkout(): void
    {
        $methods = app(PaymentMethods::class);
        $this->assertNull($methods->find('paytabs'));
        $this->configure();
        $this->assertInstanceOf(PayTabsGateway::class, $methods->find('paytabs'));
        app(PayTabsSettings::class)->update(['enabled' => false]);
        $this->assertNull($methods->find('paytabs'));
        $this->assertNotNull($methods->gatewayForProvider('paytabs'));
    }

    public function test_checkout_uses_server_amount_selected_methods_region_and_prefill(): void
    {
        $payment = $this->checkout(['region' => 'ksa', 'all_methods' => false, 'payment_methods' => ['creditcard', 'mada'], 'language' => 'ar', 'send_shipping' => true, 'hide_shipping' => true]);
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->payment_status);
        Http::assertSent(fn ($request) => $request->url() === 'https://secure.paytabs.sa/payment/request'
            && $request->hasHeader('Authorization', self::KEY) && $request['profile_id'] === 12345
            && $request['cart_amount'] === '25.00' && $request['cart_currency'] === 'USD'
            && $request['payment_methods'] === ['creditcard', 'mada'] && $request['paypage_lang'] === 'ar'
            && $request['hide_shipping'] === true && isset($request['shipping_details']['street1'])
            && $request['callback'] === route('webhooks.payments', 'paytabs') && str_starts_with($request['return'], 'https://'));
    }

    public function test_duplicate_signed_callbacks_confirm_once_without_storing_customer_data(): void
    {
        $payment = $this->checkout();
        $data = $this->transaction($payment);
        $this->postCallback($data)->assertOk();
        $this->postCallback($data)->assertOk();
        $this->assertSame(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
        $this->assertSame(1, $payment->order->invoice()->count());
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertStringNotContainsString('private@example.com', json_encode(PaymentWebhookEvent::query()->first()->payload));
        Http::assertSentCount(2); // create and one verified query
    }

    public function test_tampered_callbacks_wrong_profiles_and_disabled_notifications_are_rejected(): void
    {
        $payment = $this->checkout();
        $this->postCallback($this->transaction($payment), str_repeat('0', 64))->assertStatus(400);
        $this->postCallback($this->transaction($payment, ['profileId' => 99999]))->assertStatus(400);
        app(PayTabsSettings::class)->update(['callbacks_enabled' => false]);
        $this->postCallback($this->transaction($payment))->assertStatus(400);
        $this->assertSame(0, PaymentWebhookEvent::query()->count());
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
    }

    public function test_callback_status_cannot_override_server_and_core_detects_amount_mismatch(): void
    {
        $payment = $this->checkout();
        $this->fakeApi(fn () => Http::response($this->transaction($payment, ['cart_amount' => '0.01'])));
        $this->postCallback($this->transaction($payment))->assertOk();
        $this->assertSame(Payment::STATUS_MISMATCH, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
    }

    public function test_held_sale_is_processing_and_authorization_is_never_treated_as_a_sale(): void
    {
        $payment = $this->checkout();
        $this->fakeApi(fn () => Http::response($this->transaction($payment, ['payment_result' => ['response_status' => 'H']])));
        app(PaymentReconciler::class)->reconcile($payment);
        $this->assertSame(Payment::STATUS_PROCESSING, $payment->fresh()->status);
        $this->assertSame(0, $payment->order->invoice()->count());
        $this->fakeApi(fn () => Http::response($this->transaction($payment, ['tran_type' => 'Auth'])));
        $this->expectException(GatewayException::class);
        app(PayTabsGateway::class)->retrieve($payment);
    }

    public function test_browser_return_signature_and_owner_are_verified_before_core_confirmation(): void
    {
        $payment = $this->checkout();
        $fields = ['cartId' => app(PayTabsGateway::class)->context($payment)->cart_id, 'tranRef' => $payment->provider_reference, 'respStatus' => 'A', 'respMessage' => 'Authorised', 'token' => ''];
        $signed = $fields;
        unset($signed['token']);
        ksort($signed);
        $fields['signature'] = hash_hmac('sha256', http_build_query($signed), self::KEY);
        $this->post(route('modrixcommerce.paytabs.return', $payment), $fields)->assertStatus(303)->assertRedirect(route('checkout.payment.return', $payment->order));
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
        $this->get(route('checkout.payment.return', $payment->order))->assertRedirect();
        $this->assertSame(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
        $fields['cartId'] = 'another-cart';
        $this->post(route('modrixcommerce.paytabs.return', $payment), $fields)->assertStatus(400);
        $this->withSession([CheckoutController::SESSION_ORDERS => []])->get(route('checkout.payment.return', $payment->order))->assertNotFound();
    }

    public function test_return_signature_matches_paytabs_documented_example(): void
    {
        $fields = [];
        parse_str('acquirerMessage=&acquirerRRN=&cartId=cart_11111&customerEmail=email%40domain.com&respCode=G84718&respMessage=Authorised&respStatus=A&signature=7a181a32c768621eb6966107752ee70205a01f1c4403a3d13c0ff604f591f988&token=&tranRef=TST2215201242166', $fields);
        $this->assertTrue(PayTabsSignature::returnMatches($fields, 'SGJNZ96JLG-JDMKHGRWT9-RWRK2KJNRJ'));
    }

    public function test_mode_and_region_switch_preserve_original_payment_profile(): void
    {
        $payment = $this->checkout();
        app(PayTabsSettings::class)->update(['test_mode' => false, 'region' => 'ksa', 'enabled' => false]);
        $this->fakeApi();
        app(PaymentReconciler::class)->reconcile($payment);
        $this->assertSame(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
        Http::assertSent(fn ($request) => $request->url() === 'https://secure.paytabs.com/payment/query' && $request['profile_id'] === 12345 && $request->hasHeader('Authorization', self::KEY));
    }

    public function test_credential_replacement_stops_old_payments_without_sending_them_to_another_profile(): void
    {
        $payment = $this->checkout();
        app(PayTabsSettings::class)->update(['test_server_key' => 'different-key-12345678']);
        $this->fakeApi();
        try {
            app(PayTabsGateway::class)->retrieve($payment);
            $this->fail('Changed credentials must require reconciliation.');
        } catch (GatewayException) {
            Http::assertNothingSent();
        }
    }

    public function test_admin_settings_mask_encrypt_preserve_and_never_flash_server_keys(): void
    {
        $this->configure();
        $this->actingAsConfirmed($this->owner())->get(route('admin.modrixcommerce.paytabs.index'))
            ->assertInertia(fn (Assert $page) => $page->where('settings.test_server_key_saved', true)->missing('settings.server_key')->missing('settings.test_server_key'));
        $data = array_intersect_key(app(PayTabsSettings::class)->toArray(), PayTabsSettings::DEFAULTS);
        $this->put(route('admin.modrixcommerce.paytabs.update'), $data + ['server_key' => '', 'test_server_key' => ''])->assertSessionHasNoErrors();
        $this->assertSame(self::KEY, app(PayTabsSettings::class)->serverKey(true));
        $this->assertStringNotContainsString(self::KEY, app(SettingsRepository::class)->get('secrets.paytabs.test_server_key'));
        $invalid = array_replace($data, ['label' => '', 'server_key' => 'private-key-12345678', 'test_server_key' => self::KEY]);
        $this->put(route('admin.modrixcommerce.paytabs.update'), $invalid)->assertSessionHasErrors('label')->assertSessionMissing('_old_input.server_key')->assertSessionMissing('_old_input.test_server_key');
        $this->put(route('admin.modrixcommerce.paytabs.update'), array_replace($data, ['all_methods' => false, 'payment_methods' => []]))->assertSessionHasErrors('payment_methods');
        $this->actingAs($this->userWithRole('store-manager'))->put(route('admin.modrixcommerce.paytabs.update'), $data)->assertForbidden();
    }

    public function test_core_partial_refund_and_later_signed_confirmation_are_idempotent(): void
    {
        $payment = $this->paid();
        app(PayTabsSettings::class)->update(['refunds_enabled' => true]);
        $this->fakeApi(fn () => Http::response($this->refundData($payment, 'P')));
        $this->actingAs($this->owner())->post(route('admin.orders.refunds.store', $payment->order), ['amount' => '5.00', 'reason' => 'Return'])->assertSessionHasNoErrors();
        $refund = Refund::query()->firstOrFail();
        $this->assertSame(Refund::STATUS_PENDING, $refund->status);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->order->fresh()->payment_status);
        $this->fakeApi(fn () => Http::response($this->refundData($payment)));
        $this->postCallback($this->refundData($payment))->assertOk();
        $this->postCallback($this->refundData($payment))->assertOk();
        $this->assertSame(Refund::STATUS_COMPLETED, $refund->fresh()->status);
        $this->assertSame('5.0000', $payment->order->fresh()->refunded_total);
        $this->assertSame(1, Refund::query()->count());
    }

    public function test_repeated_refund_key_returns_existing_result_without_another_provider_call(): void
    {
        $payment = $this->paid();
        app(PayTabsSettings::class)->update(['refunds_enabled' => true]);
        $this->fakeApi(fn () => Http::response($this->refundData($payment)));
        $gateway = app(PayTabsGateway::class);
        $first = $gateway->refund($payment, Money::of('5', 'USD'), 'refund-stable-key');
        $second = $gateway->refund($payment, Money::of('5', 'USD'), 'refund-stable-key');
        $this->assertSame($first->reference, $second->reference);
        Http::assertSentCount(1);
        $this->expectException(GatewayException::class);
        $gateway->refund($payment, Money::of('6', 'USD'), 'refund-stable-key');
    }

    public function test_ambiguous_refund_is_blocked_until_explicit_verified_reconciliation(): void
    {
        $payment = $this->paid();
        app(PayTabsSettings::class)->update(['refunds_enabled' => true]);
        $attempts = 0;
        $this->fakeApi(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Timeout');
        });
        $gateway = app(PayTabsGateway::class);
        foreach (['uncertain-refund', 'uncertain-refund', 'another-refund'] as $key) {
            try {
                $gateway->refund($payment, Money::of('5', 'USD'), $key);
                $this->fail('An uncertain refund must not be resent.');
            } catch (GatewayException) {
                // Every retry remains reserved, including with a different request key.
            }
        }
        $this->assertSame(1, $attempts);
        $this->assertSame('sending', DB::table('paytabs_refund_requests')->first()->status);
        $this->fakeApi(fn () => Http::response($this->refundData($payment)));
        $this->actingAsConfirmed($this->owner())->post(route('admin.modrixcommerce.paytabs.refunds.reconcile'), ['payment_id' => $payment->id, 'reference' => 'TSTREFUND000001'])->assertSessionHasNoErrors();
        $this->assertSame('complete', DB::table('paytabs_refund_requests')->first()->status);
        $this->assertSame(Refund::STATUS_COMPLETED, Refund::query()->firstOrFail()->status);
    }

    public function test_disabled_refunds_never_send_a_financial_request(): void
    {
        $payment = $this->paid();
        $this->fakeApi();
        try {
            app(PayTabsGateway::class)->refund($payment, Money::of('5', 'USD'), 'disabled-refund');
            $this->fail('Refunds must be explicitly enabled.');
        } catch (GatewayException $e) {
            $this->assertStringContainsString('disabled', $e->getMessage());
            Http::assertNothingSent();
            $this->assertSame(0, DB::table('paytabs_refund_requests')->count());
        }
    }

    public function test_notifications_and_address_prefill_can_be_disabled_independently(): void
    {
        $this->checkout(['callbacks_enabled' => false, 'send_billing' => false, 'send_shipping' => false]);
        Http::assertSent(fn ($request) => ! isset($request['callback']) && ! isset($request['customer_details'])
            && ! isset($request['shipping_details']) && $request['payment_methods'] === ['all']);
    }

    public function test_foreign_transaction_profile_cart_and_reference_are_rejected(): void
    {
        $payment = $this->checkout();
        foreach ([['profileId' => 99999], ['cart_id' => 'foreign-cart'], ['tran_ref' => 'TSTDIFFERENT0001']] as $override) {
            $this->fakeApi(fn () => Http::response($this->transaction($payment, $override)));
            try {
                app(PayTabsGateway::class)->retrieve($payment);
                $this->fail('A different provider transaction must be rejected.');
            } catch (GatewayException) {
                $this->assertSame(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
            }
        }
    }

    public function test_provider_redirect_must_remain_on_the_selected_paytabs_host(): void
    {
        $this->configure();
        $this->fakeApi(fn ($request) => Http::response(['tran_ref' => 'TSTPAY000000001', 'profileId' => 12345, 'cart_id' => $request['cart_id'], 'redirect_url' => 'https://secure.paytabs.com.attacker.test/payment/page/123456789']));
        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'paytabs']))->assertRedirect();
        $this->assertSame(Payment::STATUS_FAILED, Payment::query()->firstOrFail()->status);
        $this->assertSame(PaymentStatus::Unpaid, Payment::query()->firstOrFail()->order->payment_status);
    }
}
