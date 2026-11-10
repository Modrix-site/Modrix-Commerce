<?php

namespace Tests\Feature\Payments;

use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Gateways\Stripe\StripeSettings;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Models\PaymentWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\StripeFixtures;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, StripeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_stripe_is_offered_only_when_enabled_with_credentials()
    {
        $this->addToCart($this->sellable());
        $codes = fn () => collect($this->get(route('checkout.show'))->viewData('page')['props']['paymentMethods'])->pluck('code')->all();

        $this->assertNotContains('stripe', $codes());

        $this->configureStripe(enabled: false);
        $this->assertNotContains('stripe', $codes());

        $this->configureStripe();
        $this->assertContains('stripe', $codes());
    }

    public function test_checkout_redirects_to_the_hosted_page_without_marking_the_order_paid()
    {
        $this->configureStripe();
        $this->fakeStripeApi();

        $variant = $this->sellable('12.50', 10);
        $this->addToCart($variant, 2);
        $response = $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'stripe']));

        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_fake');
        $order = Order::query()->firstOrFail();
        $payment = $order->payments()->firstOrFail();

        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertStringStartsWith('cs_test_', (string) $payment->provider_reference);
        $this->assertSame('30.0000', $payment->amount); // 2 × 12.50 + 5.00 shipping

        Http::assertSent(function (HttpRequest $request) use ($order, $payment) {
            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && $request['line_items'][0]['price_data']['unit_amount'] === 3000
                && $request['line_items'][0]['price_data']['currency'] === 'usd'
                && $request['client_reference_id'] === $order->number
                && $request->hasHeader('Idempotency-Key', "ecomodular-payment-{$payment->id}")
                && $request->hasHeader('Stripe-Version', '2024-06-20')
                && $request->hasHeader('Authorization', 'Bearer '.$this->stripeSecretKey);
        });

        // Stock is held while the customer pays; the confirmation email waits for the payment.
        $this->assertSame(8, $variant->fresh()->onHand());
        Mail::assertNothingSent();
    }

    public function test_apple_pay_and_google_pay_switches_are_sent_to_stripe()
    {
        $this->configureStripe();
        app(StripeSettings::class)->update(true, 'Card', null, null, true, false);
        $this->fakeStripeApi();
        $this->addToCart($this->sellable());

        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'stripe']))
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_fake');

        Http::assertSent(function (HttpRequest $request) {
            return str_contains($request->url(), '/v1/payment_method_configurations')
                && ($request['apple_pay']['display_preference']['preference'] ?? null) === 'on'
                && ($request['google_pay']['display_preference']['preference'] ?? null) === 'off';
        });
        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && $request['payment_method_configuration'] === 'pmc_test_wallets';
        });
    }

    public function test_an_inertia_checkout_gets_an_external_location_response()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        $this->addToCart($this->sellable());

        $this->withHeaders(['X-Inertia' => 'true'])
            ->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'stripe']))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/c/pay/cs_test_fake');
    }

    public function test_the_return_redirect_alone_never_marks_an_order_paid()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder();

        // Stripe still reports the session as open and unpaid.
        Http::fake(['api.stripe.com/v1/checkout/sessions/*' => Http::response(['id' => $payment->provider_reference, 'status' => 'open', 'payment_status' => 'unpaid', 'amount_total' => 2500, 'currency' => 'usd'])]);

        $this->get(route('checkout.payment.return', [$order, 'session_id' => $payment->provider_reference, 'paid' => 1]))
            ->assertRedirect(route('checkout.complete', $order));

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->get(route('checkout.complete', $order))
            ->assertInertia(fn (Assert $page) => $page->where('onlinePayment.state', 'confirming'));
    }

    public function test_return_page_confirms_the_payment_by_asking_stripe_server_side()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder();

        Http::fake(['api.stripe.com/v1/checkout/sessions/*' => Http::response($this->completedSession($payment))]);

        $this->get(route('checkout.payment.return', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame('pi_test_'.$payment->id, $payment->fresh()->provider_payment_id);
        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'placed');
    }

    public function test_a_server_reported_amount_that_differs_is_not_applied()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder('20.00');

        $this->postStripeWebhook($this->stripeEvent('checkout.session.completed', $this->completedSession($payment, amountTotal: 100)))->assertOk();

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_MISMATCH, $payment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.amount_mismatch']);

        // A currency mismatch is caught the same way.
        [$order2, $payment2] = $this->placeStripeOrder('20.00');
        $this->postStripeWebhook($this->stripeEvent('checkout.session.completed', $this->completedSession($payment2, currency: 'eur')))->assertOk();
        $this->assertSame(PaymentStatus::Unpaid, $order2->fresh()->payment_status);
    }

    public function test_completed_webhook_marks_the_order_paid_once_even_when_redelivered()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder('20.00');
        $event = $this->stripeEvent('checkout.session.completed', $this->completedSession($payment));

        $this->postStripeWebhook($event)->assertOk()->assertJson(['status' => 'accepted']);
        $this->postStripeWebhook($event)->assertOk()->assertJson(['status' => 'duplicate']);
        // Stripe can also send a different event id about the same session.
        $this->postStripeWebhook($this->stripeEvent('checkout.session.completed', $this->completedSession($payment)))->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('25.0000', $order->paid_total);
        $this->assertSame(1, $order->payments()->where('status', Payment::STATUS_PAID)->count());
        $this->assertSame(2, PaymentWebhookEvent::query()->count());
        $this->assertSame(1, PaymentWebhookEvent::query()->where('event_id', $event['id'])->count());
        Mail::assertSent(OrderMail::class, fn (OrderMail $m) => $m->kind === 'placed');
        $this->assertSame(1, $order->events()->where('type', 'email')->get()->filter(fn ($e) => ($e->metadata['key'] ?? null) === 'placed')->count());
    }

    public function test_forged_and_stale_webhooks_are_rejected_before_anything_is_stored()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder();
        $event = $this->stripeEvent('checkout.session.completed', $this->completedSession($payment));

        $this->postStripeWebhook($event, secret: 'whsec_attacker_guess_000000')->assertStatus(400);
        $this->postStripeWebhook($event, timestamp: time() - 3600)->assertStatus(400);
        $this->postStripeWebhook($event, signature: '')->assertStatus(400);
        $this->postStripeWebhook($event, signature: 't='.time().',v0=deadbeef')->assertStatus(400);

        $this->assertSame(0, PaymentWebhookEvent::query()->count());
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_webhooks_for_unknown_providers_and_foreign_sessions_are_ignored()
    {
        $this->configureStripe();

        $this->postJson('/webhooks/payments/paypal', [])->assertNotFound();

        $this->postStripeWebhook($this->stripeEvent('checkout.session.completed', [
            'id' => 'cs_created_by_another_app', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 999, 'currency' => 'usd',
        ]))->assertOk();
        $this->postStripeWebhook($this->stripeEvent('customer.created', ['id' => 'cus_1']))->assertOk();

        $this->assertSame(2, PaymentWebhookEvent::query()->where('status', PaymentWebhookEvent::STATUS_IGNORED)->count());
    }

    public function test_an_expired_session_cancels_the_order_and_releases_stock_without_emailing()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder('20.00', 3);
        $variantId = $order->items()->value('variant_id');
        $this->assertSame(7, (int) InventoryStock::query()->where('variant_id', $variantId)->value('on_hand'));

        $this->postStripeWebhook($this->stripeEvent('checkout.session.expired', ['id' => $payment->provider_reference, 'status' => 'expired', 'payment_status' => 'unpaid']))->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertSame(10, (int) InventoryStock::query()->where('variant_id', $variantId)->value('on_hand'));
        Mail::assertNothingSent();
    }

    public function test_a_payment_that_arrives_after_cancellation_is_kept_and_flagged_for_refund()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder('20.00');

        $this->postStripeWebhook($this->stripeEvent('checkout.session.expired', ['id' => $payment->provider_reference, 'status' => 'expired']))->assertOk();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);

        // Out of order: the payment completed just before the session expired.
        $payment->forceFill(['status' => Payment::STATUS_PENDING])->save();
        $this->postStripeWebhook($this->stripeEvent('checkout.session.completed', $this->completedSession($payment)))->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('25.00', $order->refundable()->toDisplayDecimal());
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.paid_after_cancellation']);
    }

    public function test_an_expiry_that_arrives_after_payment_changes_nothing()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder();

        $this->postStripeWebhook($this->stripeEvent('checkout.session.completed', $this->completedSession($payment)))->assertOk();
        $this->postStripeWebhook($this->stripeEvent('checkout.session.expired', ['id' => $payment->provider_reference, 'status' => 'expired']))->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_delayed_payment_methods_go_through_processing_then_succeed_or_fail()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder();

        $this->postStripeWebhook($this->stripeEvent('checkout.session.completed', $this->completedSession($payment, paymentStatus: 'unpaid')))->assertOk();
        $this->assertSame(Payment::STATUS_PROCESSING, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->get(route('checkout.complete', $order))->assertInertia(fn (Assert $page) => $page->where('onlinePayment.state', 'processing'));

        $this->postStripeWebhook($this->stripeEvent('checkout.session.async_payment_succeeded', $this->completedSession($payment)))->assertOk();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        [$order2, $payment2] = $this->placeStripeOrder();
        $this->postStripeWebhook($this->stripeEvent('checkout.session.async_payment_failed', $this->completedSession($payment2, paymentStatus: 'unpaid')))->assertOk();
        $this->assertSame(Payment::STATUS_FAILED, $payment2->fresh()->status);
        $this->assertSame(PaymentStatus::Failed, $order2->fresh()->payment_status);
        $this->get(route('checkout.complete', $order2))->assertInertia(fn (Assert $page) => $page->where('onlinePayment.state', 'awaiting'));
    }

    public function test_customer_can_retry_and_the_old_session_is_closed()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder();

        // A still-valid session is reused rather than duplicated.
        $this->post(route('checkout.pay', $order))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_fake');
        $this->assertSame(1, $order->payments()->count());

        // Once it is about to expire, a new session replaces it and the old one is closed at Stripe.
        $payment->forceFill(['expires_at' => now()->addMinute()])->save();
        $this->post(route('checkout.pay', $order))->assertRedirect();

        $this->assertSame(2, $order->payments()->count());
        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), "/v1/checkout/sessions/{$payment->provider_reference}/expire"));

        // Paying the replaced session's expiry later does not cancel the order.
        $this->postStripeWebhook($this->stripeEvent('checkout.session.expired', ['id' => $payment->provider_reference, 'status' => 'expired']))->assertOk();
        $this->assertNotSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_a_paid_order_cannot_be_paid_again()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder();
        $this->postStripeWebhook($this->stripeEvent('checkout.session.completed', $this->completedSession($payment)))->assertOk();

        $this->post(route('checkout.pay', $order))->assertRedirect(route('checkout.complete', $order));
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_a_stripe_outage_at_checkout_keeps_the_order_and_lets_the_customer_retry()
    {
        $this->configureStripe();
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::sequence()
            ->push(['error' => ['message' => 'Service unavailable']], 503)
            ->push(['id' => 'cs_test_retry', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_retry', 'expires_at' => now()->addMinutes(30)->getTimestamp()]),
        ]);

        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'stripe']));

        $order = Order::query()->firstOrFail();
        $this->assertSame(Payment::STATUS_FAILED, $order->payments()->value('status'));
        $this->get(route('checkout.complete', $order))->assertInertia(fn (Assert $page) => $page->where('onlinePayment.state', 'awaiting'));

        $this->post(route('checkout.pay', $order))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_retry');
    }

    public function test_other_visitors_cannot_pay_or_reconcile_someone_elses_order()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order] = $this->placeStripeOrder();

        $this->flushSession();

        $this->post(route('checkout.pay', $order))->assertNotFound();
        $this->get(route('checkout.payment.return', $order))->assertNotFound();
    }

    public function test_reconcile_command_applies_the_state_of_open_payments()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder();
        $payment->forceFill(['created_at' => now()->subMinutes(10)])->save();

        Http::fake(['api.stripe.com/v1/checkout/sessions/*' => Http::response($this->completedSession($payment))]);

        $this->artisan('ecomodular:payments:reconcile')->assertSuccessful();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_secret_keys_never_reach_the_browser()
    {
        $this->configureStripe();
        $this->fakeStripeApi();
        $this->addToCart($this->sellable());

        $checkout = $this->get(route('checkout.show'))->getContent();
        $admin = $this->actingAsConfirmed($this->owner())->get(route('admin.payments.stripe.edit'))->getContent();

        foreach ([$checkout, $admin] as $html) {
            $this->assertStringNotContainsString($this->stripeSecretKey, (string) $html);
            $this->assertStringNotContainsString($this->stripeWebhookSecret, (string) $html);
        }

        $stored = DB::table('settings')->where('key', 'secrets.stripe.secret_key')->value('value');
        $this->assertStringNotContainsString($this->stripeSecretKey, (string) $stored);
    }
}
