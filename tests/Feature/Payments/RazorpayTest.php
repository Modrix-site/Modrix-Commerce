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
use ModrixCommerce\Razorpay\RazorpayAmount;
use ModrixCommerce\Razorpay\RazorpayGateway;
use ModrixCommerce\Razorpay\RazorpaySettings;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class RazorpayTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const SECRET = 'test-api-secret-123456';

    private const WEBHOOK = 'test-webhook-secret-123456';

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
        File::copyDirectory(base_path('modules/modrixcommerce/razorpay'), $this->modulesPath.'/modrixcommerce/razorpay');
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('modrixcommerce/razorpay');
        app(ModuleManager::class)->enable('modrixcommerce/razorpay', true);
    }

    private function configure(array $overrides = []): void
    {
        app(RazorpaySettings::class)->update(array_replace([
            'enabled' => true, 'test_key_id' => 'rzp_test_1234567890ABCD', 'test_key_secret' => self::SECRET,
            'key_id' => 'rzp_live_ABCDEFGHIJKLMN', 'key_secret' => 'live-api-secret-123456',
            'test_webhook_secret' => self::WEBHOOK, 'webhook_secret' => 'live-webhook-secret-123456', 'currencies' => ['USD'],
        ], $overrides));
    }

    private function entity(Payment $payment, array $overrides = []): array
    {
        return array_replace([
            'id' => 'pay_TEST123456789', 'entity' => 'payment', 'order_id' => $payment->provider_reference,
            'amount' => 2500, 'currency' => 'USD', 'status' => 'captured', 'captured' => true,
            'email' => 'private@example.com', 'contact' => '555123456',
        ], $overrides);
    }

    private function fakeApi(array $entityOverrides = [], ?callable $custom = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($entityOverrides, $custom) {
            if ($custom !== null && ($result = $custom($request)) !== null) {
                return $result;
            }
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'POST' && $path === '/v1/orders') {
                return Http::response(['id' => 'order_TEST123456789', 'entity' => 'order', 'receipt' => $request['receipt'], 'amount' => $request['amount'], 'currency' => $request['currency'], 'status' => 'created']);
            }
            $payment = Payment::query()->where('provider', 'razorpay')->firstOrFail();
            if (str_ends_with($path, '/payments')) {
                return Http::response(['entity' => 'collection', 'count' => 1, 'items' => [$this->entity($payment, $entityOverrides)]]);
            }
            if (str_ends_with($path, '/capture')) {
                return Http::response($this->entity($payment));
            }
            if (str_starts_with($path, '/v1/orders/')) {
                return Http::response(['id' => $payment->provider_reference, 'entity' => 'order', 'receipt' => app(RazorpayGateway::class)->context($payment)->receipt, 'amount' => 2500, 'currency' => 'USD', 'status' => 'paid']);
            }
            throw new \RuntimeException('Unmocked Razorpay operation.');
        });
    }

    private function checkout(array $settings = []): Payment
    {
        $this->configure($settings);
        $this->fakeApi();
        $this->addToCart($this->sellable('20.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'razorpay']))->assertSessionHasNoErrors()->assertRedirect();

        return Payment::query()->where('provider', 'razorpay')->firstOrFail();
    }

    private function paid(): Payment
    {
        $payment = $this->checkout();
        app(PaymentReconciler::class)->reconcile($payment);

        return $payment->refresh();
    }

    private function refundEntity(Payment $payment, string $status = 'processed', array $overrides = []): array
    {
        return array_replace(['id' => 'rfnd_TEST123456789', 'entity' => 'refund', 'payment_id' => $payment->provider_payment_id, 'amount' => 500, 'currency' => 'USD', 'status' => $status], $overrides);
    }

    private function postEvent(array $data, ?string $signature = null)
    {
        $payload = json_encode($data);

        return $this->call('POST', route('webhooks.payments', 'razorpay'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $payload, self::WEBHOOK)], $payload);
    }

    private function paymentEvent(Payment $payment, string $type = 'payment.captured'): array
    {
        return ['event' => $type, 'payload' => ['payment' => ['entity' => $this->entity($payment)]]];
    }

    public function test_lifecycle_and_settings_gate_checkout_but_preserve_old_order_processing(): void
    {
        $methods = app(PaymentMethods::class);
        $this->assertNull($methods->find('razorpay'));
        $this->configure();
        $this->assertInstanceOf(RazorpayGateway::class, $methods->find('razorpay'));
        app(RazorpaySettings::class)->update(['enabled' => false]);
        $this->assertNull($methods->find('razorpay'));
        $this->assertNotNull($methods->gatewayForProvider('razorpay'));
    }

    public function test_server_order_and_owned_checkout_options_use_exact_core_amount_without_secrets(): void
    {
        $payment = $this->checkout(['all_methods' => false, 'payment_methods' => ['card', 'upi'], 'retry_enabled' => false, 'theme_color' => '#123456']);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://api.razorpay.com/v1/orders'
            && $request['amount'] === 2500 && $request['currency'] === 'USD' && $request['partial_payment'] === false
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_1234567890ABCD:'.self::SECRET)));
        $this->get($payment->checkout_url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page->component('module/modrixcommerce/razorpay/storefront/checkout', false)
                ->where('options.order_id', $payment->provider_reference)->where('options.amount', 2500)->where('options.key', 'rzp_test_1234567890ABCD')
                ->where('options.retry.enabled', false)->where('options.theme.color', '#123456')->where('options.config.display.preferences.show_default_blocks', false)
                ->missing('options.key_secret')->missing('options.webhook_secret'));
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->payment_status);
        $this->withSession([CheckoutController::SESSION_ORDERS => []])->get($payment->checkout_url)->assertNotFound();
    }

    public function test_expired_handoff_is_unavailable_and_prefill_can_be_disabled(): void
    {
        $payment = $this->checkout(['prefill_customer' => false, 'auto_open' => false]);
        $this->get($payment->checkout_url)->assertInertia(fn (Assert $page) => $page->missing('options.prefill')->where('autoOpen', false));
        $payment->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->get($payment->checkout_url)->assertRedirect(route('checkout.complete', $payment->order));
    }

    public function test_duplicate_signed_capture_webhooks_confirm_and_invoice_once_without_pii(): void
    {
        $payment = $this->checkout();
        $event = $this->paymentEvent($payment);
        $this->postEvent($event)->assertOk();
        $this->postEvent($event)->assertOk();
        $this->assertSame(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
        $this->assertSame(1, $payment->order->invoice()->count());
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertStringNotContainsString('private@example.com', json_encode(PaymentWebhookEvent::query()->first()->payload));
        Http::assertSentCount(3); // order creation, order fetch and payment collection
    }

    public function test_tampered_disabled_and_wrong_mode_webhooks_cannot_confirm_payments(): void
    {
        $payment = $this->checkout();
        $event = $this->paymentEvent($payment);
        $this->postEvent($event, str_repeat('0', 64))->assertStatus(400);
        $this->postEvent($event, hash_hmac('sha256', json_encode($event), 'live-webhook-secret-123456'))->assertOk();
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
        app(RazorpaySettings::class)->update(['webhooks_enabled' => false]);
        $this->postEvent($event)->assertStatus(400);
    }

    public function test_same_webhook_secret_in_both_modes_is_supported(): void
    {
        $payment = $this->checkout(['webhook_secret' => self::WEBHOOK]);
        $this->postEvent($this->paymentEvent($payment))->assertOk();
        $this->assertSame(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
    }

    public function test_authorized_is_processing_until_captured(): void
    {
        $payment = $this->checkout();
        $this->fakeApi(['status' => 'authorized', 'captured' => false]);
        app(PaymentReconciler::class)->reconcile($payment);
        $this->assertSame(Payment::STATUS_PROCESSING, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
        $this->assertSame(0, $payment->order->invoice()->count());
        $this->fakeApi();
        app(PaymentReconciler::class)->reconcile($payment->fresh());
        $this->assertSame(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
    }

    public function test_optional_capture_uses_verified_full_amount_and_requires_captured_response(): void
    {
        $payment = $this->checkout(['capture_authorized' => true]);
        $this->fakeApi(['status' => 'authorized', 'captured' => false]);
        app(PaymentReconciler::class)->reconcile($payment);
        $this->assertSame(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/capture') && $request['amount'] === 2500 && $request['currency'] === 'USD');
    }

    public function test_failed_attempt_does_not_fail_a_retryable_order_and_mismatches_are_held(): void
    {
        $payment = $this->checkout();
        $this->fakeApi(['status' => 'failed', 'captured' => false]);
        app(PaymentReconciler::class)->reconcile($payment);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->fakeApi(['amount' => 1]);
        app(PaymentReconciler::class)->reconcile($payment->fresh());
        $this->assertSame(Payment::STATUS_MISMATCH, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
    }

    public function test_browser_return_signature_binds_server_order_and_does_not_confirm_authorization(): void
    {
        $payment = $this->checkout();
        $fields = ['razorpay_order_id' => $payment->provider_reference, 'razorpay_payment_id' => 'pay_TEST123456789', 'razorpay_signature' => hash_hmac('sha256', $payment->provider_reference.'|pay_TEST123456789', self::SECRET)];
        $this->post(route('modrixcommerce.razorpay.return', $payment), $fields)->assertStatus(303)->assertRedirect(route('checkout.payment.return', $payment->order));
        $this->assertSame(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
        $this->get(route('checkout.payment.return', $payment->order))->assertRedirect();
        $this->assertSame(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
        $fields['razorpay_order_id'] = 'order_foreign123';
        $this->post(route('modrixcommerce.razorpay.return', $payment), $fields)->assertStatus(400);
        $this->withSession([CheckoutController::SESSION_ORDERS => []])->get(route('checkout.payment.return', $payment->order))->assertNotFound();
    }

    public function test_old_payments_keep_their_original_mode_and_changed_credentials_fail_closed(): void
    {
        $payment = $this->checkout();
        app(RazorpaySettings::class)->update(['test_mode' => false, 'enabled' => false]);
        $this->fakeApi();
        app(PaymentReconciler::class)->reconcile($payment);
        $this->assertSame(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_1234567890ABCD:'.self::SECRET)));
        app(RazorpaySettings::class)->update(['test_key_secret' => 'changed-key-secret-123']);
        $this->fakeApi();
        try {
            app(RazorpayGateway::class)->retrieve($payment->fresh());
            $this->fail('Rotated credentials must require restoration.');
        } catch (GatewayException) {
            Http::assertNothingSent();
        }
    }

    public function test_admin_credentials_are_masked_encrypted_preserved_and_never_flashed(): void
    {
        $this->configure();
        $this->actingAsConfirmed($this->owner())->get(route('admin.modrixcommerce.razorpay.index'))
            ->assertInertia(fn (Assert $page) => $page->where('settings.test_key_secret_saved', true)->missing('settings.key_secret')->missing('settings.test_key_secret')->missing('settings.test_webhook_secret'));
        $data = array_intersect_key(app(RazorpaySettings::class)->toArray(), RazorpaySettings::DEFAULTS);
        $this->put(route('admin.modrixcommerce.razorpay.update'), $data + ['test_key_secret' => ''])->assertSessionHasNoErrors();
        $this->assertSame(self::SECRET, app(RazorpaySettings::class)->secret('key_secret', true));
        $this->assertStringNotContainsString(self::SECRET, app(SettingsRepository::class)->get('secrets.razorpay.test_key_secret'));
        $this->put(route('admin.modrixcommerce.razorpay.update'), array_replace($data, ['label' => '', 'test_key_secret' => self::SECRET, 'test_webhook_secret' => self::WEBHOOK]))
            ->assertSessionHasErrors('label')->assertSessionMissing('_old_input.test_key_secret')->assertSessionMissing('_old_input.test_webhook_secret');
        $this->put(route('admin.modrixcommerce.razorpay.update'), array_replace($data, ['test_key_id' => 'rzp_live_WRONG12345']))->assertSessionHasErrors('test_key_id');
        $this->actingAs($this->userWithRole('store-manager'))->put(route('admin.modrixcommerce.razorpay.update'), $data)->assertForbidden();
    }

    public function test_minor_units_are_exact_including_zero_and_three_decimal_currencies(): void
    {
        $this->assertSame(2501, RazorpayAmount::toMinor(Money::of('25.01', 'INR')));
        $this->assertSame(25, RazorpayAmount::toMinor(Money::of('25', 'JPY')));
        $this->assertSame(25990, RazorpayAmount::toMinor(Money::of('25.990', 'KWD')));
        $this->assertTrue(RazorpayAmount::money(25990, 'KWD')->equals(Money::of('25.990', 'KWD')));
        $this->expectException(GatewayException::class);
        RazorpayAmount::toMinor(Money::of('25.991', 'KWD'));
    }

    public function test_core_partial_refund_and_signed_completion_are_applied_once(): void
    {
        $payment = $this->paid();
        app(RazorpaySettings::class)->update(['refunds_enabled' => true]);
        $this->fakeApi(custom: fn () => Http::response($this->refundEntity($payment, 'pending')));
        $this->actingAs($this->owner())->post(route('admin.orders.refunds.store', $payment->order), ['amount' => '5.00', 'reason' => 'Return'])->assertSessionHasNoErrors();
        $refund = Refund::query()->firstOrFail();
        $this->assertSame(Refund::STATUS_PENDING, $refund->status);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->order->fresh()->payment_status);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/refund') && $request['amount'] === 500 && $request['speed'] === 'normal' && $request->hasHeader('X-Refund-Idempotency'));
        $this->fakeApi(custom: fn () => Http::response($this->refundEntity($payment)));
        $event = ['event' => 'refund.processed', 'payload' => ['refund' => ['entity' => $this->refundEntity($payment)]]];
        $this->postEvent($event)->assertOk();
        $this->postEvent($event)->assertOk();
        $this->assertSame(Refund::STATUS_COMPLETED, $refund->fresh()->status);
        $this->assertSame('5.0000', $payment->order->fresh()->refunded_total);
        $this->assertSame(1, Refund::query()->count());
    }

    public function test_refund_request_key_is_stable_and_cannot_be_reused_with_another_amount(): void
    {
        $payment = $this->paid();
        app(RazorpaySettings::class)->update(['refunds_enabled' => true]);
        $this->fakeApi(custom: fn () => Http::response($this->refundEntity($payment)));
        $gateway = app(RazorpayGateway::class);
        $first = $gateway->refund($payment, Money::of('5', 'USD'), 'stable-refund-key');
        $second = $gateway->refund($payment, Money::of('5', 'USD'), 'stable-refund-key');
        $this->assertSame($first->reference, $second->reference);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Refund-Idempotency', substr(hash('sha256', 'stable-refund-key'), 0, 32)));
        $this->expectException(GatewayException::class);
        $gateway->refund($payment, Money::of('6', 'USD'), 'stable-refund-key');
    }

    public function test_unknown_refund_outcome_blocks_resends_until_verified_admin_reconciliation(): void
    {
        $payment = $this->paid();
        app(RazorpaySettings::class)->update(['refunds_enabled' => true]);
        $attempts = 0;
        $this->fakeApi(custom: function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Timeout');
        });
        foreach (['uncertain-key', 'uncertain-key', 'different-key'] as $key) {
            try {
                app(RazorpayGateway::class)->refund($payment, Money::of('5', 'USD'), $key);
                $this->fail('Uncertain refunds must not be sent again.');
            } catch (GatewayException) {
                // The reservation remains intact across failures and different request keys.
            }
        }
        $this->assertSame(1, $attempts);
        $this->assertSame('sending', DB::table('razorpay_refund_requests')->first()->status);
        $this->fakeApi(custom: fn () => Http::response($this->refundEntity($payment)));
        // A dashboard refund of the same amount must not release an unrelated reservation.
        app(RazorpayGateway::class)->reconcileRefund($payment, 'rfnd_TEST123456789', false);
        $this->assertSame('sending', DB::table('razorpay_refund_requests')->first()->status);
        $this->actingAsConfirmed($this->owner())->post(route('admin.modrixcommerce.razorpay.refunds.reconcile'), ['payment_id' => $payment->id, 'reference' => 'rfnd_TEST123456789'])->assertSessionHasNoErrors();
        $this->assertSame('complete', DB::table('razorpay_refund_requests')->first()->status);
        $this->assertSame(Refund::STATUS_COMPLETED, Refund::query()->firstOrFail()->status);
    }

    public function test_disabled_refunds_never_call_the_provider(): void
    {
        $payment = $this->paid();
        $this->fakeApi();
        try {
            app(RazorpayGateway::class)->refund($payment, Money::of('5', 'USD'), 'disabled-key');
            $this->fail('Refunds must be explicitly enabled.');
        } catch (GatewayException $e) {
            $this->assertStringContainsString('disabled', $e->getMessage());
            Http::assertNothingSent();
        }
    }
}
