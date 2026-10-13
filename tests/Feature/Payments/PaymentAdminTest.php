<?php

namespace Tests\Feature\Payments;

use App\Core\Audit\AuditLog;
use App\Domains\Payments\Gateways\Stripe\StripeSettings;
use App\Domains\Payments\Models\PaymentWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\StripeFixtures;
use Tests\TestCase;

class PaymentAdminTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, StripeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_stripe_settings_require_permission_and_password_confirmation()
    {
        $this->actingAs($this->owner())->get(route('admin.payments.stripe.edit'))->assertRedirect(route('password.confirm'));
        $this->actingAs($this->owner())->put(route('admin.payments.stripe.update'), ['label' => 'x'])->assertRedirect(route('password.confirm'));
        $this->actingAsConfirmed($this->userWithRole('store-manager'))->get(route('admin.payments.stripe.edit'))->assertForbidden();
        $this->actingAsConfirmed($this->userWithRole('administrator'))->get(route('admin.payments.stripe.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/payments/stripe')->where('stripe.secret_key_hint', null));
    }

    public function test_credentials_are_stored_encrypted_and_only_a_hint_is_shown()
    {
        $this->actingAsConfirmed($this->owner())->put(route('admin.payments.stripe.update'), [
            'enabled' => '1',
            'label' => 'Card',
            'secret_key' => $this->stripeSecretKey,
            'webhook_secret' => $this->stripeWebhookSecret,
        ])->assertSessionHasNoErrors();

        $settings = app(StripeSettings::class);
        $this->assertSame($this->stripeSecretKey, $settings->secretKey());
        $this->assertSame('test', $settings->mode());

        $this->actingAsConfirmed($this->owner())->get(route('admin.payments.stripe.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stripe.secret_key_hint', '••••0001')
                ->where('stripe.mode', 'test'));

        $audit = AuditLog::query()->where('action', 'payments.stripe_updated')->firstOrFail();
        $this->assertStringNotContainsString($this->stripeSecretKey, json_encode($audit->metadata, JSON_THROW_ON_ERROR));
        $this->assertSame(['api key', 'webhook signing key'], $audit->metadata['changed']);

        // Saving again without keys keeps the stored ones.
        $this->actingAsConfirmed($this->owner())->put(route('admin.payments.stripe.update'), ['enabled' => '1', 'label' => 'Pay by card'])->assertSessionHasNoErrors();
        $this->assertSame($this->stripeSecretKey, app(StripeSettings::class)->secretKey());
    }

    public function test_stripe_cannot_be_enabled_without_valid_credentials()
    {
        $this->actingAsConfirmed($this->owner())
            ->put(route('admin.payments.stripe.update'), ['enabled' => '1', 'label' => 'Card'])
            ->assertSessionHasErrors(['secret_key', 'webhook_secret']);

        $this->actingAsConfirmed($this->owner())
            ->put(route('admin.payments.stripe.update'), ['enabled' => '1', 'label' => 'Card', 'secret_key' => 'pk_test_publishable_not_secret', 'webhook_secret' => 'nope'])
            ->assertSessionHasErrors(['secret_key', 'webhook_secret']);

        $this->assertFalse(app(StripeSettings::class)->enabled());
    }

    public function test_connection_test_reports_stripe_errors_without_echoing_keys()
    {
        $this->configureStripe();
        Http::fake(['api.stripe.com/v1/balance' => Http::response(['error' => ['message' => "Invalid API Key provided: {$this->stripeSecretKey}"]], 401)]);

        $this->actingAsConfirmed($this->owner())->post(route('admin.payments.stripe.test'))->assertRedirect();

        $toast = session('inertia.flash_data.toast') ?? session('toast');
        $this->assertStringNotContainsString($this->stripeSecretKey, json_encode($toast, JSON_THROW_ON_ERROR) ?: '');
        Http::assertSent(fn ($r) => $r->url() === 'https://api.stripe.com/v1/balance');
    }

    public function test_payment_event_log_lists_events_and_retries_failed_ones()
    {
        $this->enableCheckout();
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder();

        $failed = new PaymentWebhookEvent;
        $failed->forceFill([
            'provider' => 'stripe',
            'event_id' => 'evt_failed_once',
            'type' => 'checkout.session.completed',
            'payload' => $this->stripeEvent('checkout.session.completed', $this->completedSession($payment), 'evt_failed_once'),
            'status' => PaymentWebhookEvent::STATUS_FAILED,
            'attempts' => 1,
            'error' => 'RuntimeException: database went away',
            'received_at' => now(),
        ])->save();

        $this->actingAs($this->userWithRole('order-manager'))->get(route('admin.payments.webhooks.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('administrator'))->get(route('admin.payments.webhooks.index', ['status' => 'failed']))
            ->assertInertia(fn (Assert $page) => $page->component('admin/payments/webhooks')->has('events.data', 1));

        $this->actingAs($this->owner())->post(route('admin.payments.webhooks.retry', $failed))->assertRedirect();

        $this->assertSame(PaymentWebhookEvent::STATUS_PROCESSED, $failed->fresh()->status);
        $this->assertSame($order->id, $failed->fresh()->order_id);
        $this->assertSame('paid', $order->fresh()->payment_status->value);
    }

    public function test_a_stripe_order_can_be_settled_manually_and_its_checkout_page_closes()
    {
        $this->enableCheckout();
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder();

        $this->actingAs($this->owner())
            ->get(route('admin.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('actions', fn ($actions) => collect($actions)->contains('record_payment')));

        $this->actingAs($this->owner())
            ->post(route('admin.orders.payments.store', $order), ['method' => 'card_terminal'])
            ->assertSessionHasNoErrors();
        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status->value);
    }
}
