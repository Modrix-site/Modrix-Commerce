<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Marketplace\Mail\LicenseIssued;
use Marketplace\Models\License;
use Marketplace\Models\Order;
use Marketplace\Models\Package;
use Marketplace\Models\Setting;
use Marketplace\Models\User;
use Tests\TestCase;

/**
 * Hosted Stripe Checkout and the signed webhook at /stripe/webhook.
 */
class StripeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    private Package $package;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('stripe_secret', 'sk_test_123', secret: true);
        Setting::put('stripe_webhook_secret', self::SECRET, secret: true);
        $this->package = Package::query()->create(['identifier' => 'acme/theme', 'type' => 'theme', 'slug' => 'acme-theme', 'name' => 'Acme Theme', 'status' => 'published', 'is_free' => false, 'price' => '49.00', 'currency' => 'USD']);
        $this->buyer = User::factory()->create();
        Mail::fake();
    }

    private function order(): Order
    {
        return Order::query()->create(['user_id' => $this->buyer->id, 'package_id' => $this->package->id, 'amount' => '49.00', 'currency' => 'USD', 'stripe_session' => 'cs_test_1', 'status' => 'pending']);
    }

    private function deliver(array $event, ?int $time = null, ?string $secret = null)
    {
        $payload = json_encode($event);
        $time ??= time();
        $signature = hash_hmac('sha256', $time.'.'.$payload, $secret ?? self::SECRET);

        return $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    private function completed(string $id = 'evt_1'): array
    {
        return ['id' => $id, 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1']]];
    }

    public function test_checkout_creates_a_pending_order_and_redirects_to_stripe()
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_9', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_9'])]);

        $this->actingAs($this->buyer)->post('/checkout/acme-theme')->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_9');

        $order = Order::query()->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('cs_test_9', $order->stripe_session);
        Http::assertSent(fn ($request) => $request['mode'] === 'payment'
            && $request['line_items'][0]['price_data']['unit_amount'] === 4900
            && $request['metadata']['order_id'] === (string) $order->id);
    }

    public function test_free_packages_have_no_checkout()
    {
        $this->package->update(['is_free' => true, 'price' => '0']);

        $this->actingAs($this->buyer)->post('/checkout/acme-theme')->assertNotFound();
    }

    public function test_a_bad_or_old_signature_changes_nothing()
    {
        $order = $this->order();

        $this->deliver($this->completed(), secret: 'whsec_wrong')->assertStatus(400);
        $this->deliver($this->completed(), time: time() - 301)->assertStatus(400);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, License::query()->count());
    }

    public function test_a_completed_payment_issues_one_license_even_if_delivered_twice()
    {
        $order = $this->order();

        $this->deliver($this->completed())->assertOk();
        $this->deliver($this->completed())->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame('pi_1', $order->stripe_payment_intent);
        $this->assertSame(1, License::query()->count());
        $this->assertSame($order->license_id, License::query()->value('id'));
        $this->assertSame('paid', License::query()->value('type'));
        Mail::assertQueued(LicenseIssued::class, 1);
    }

    public function test_an_expired_session_marks_the_order_expired()
    {
        $order = $this->order();

        $this->deliver(['id' => 'evt_2', 'type' => 'checkout.session.expired', 'data' => ['object' => ['id' => 'cs_test_1']]])->assertOk();

        $this->assertSame('expired', $order->fresh()->status);
    }

    public function test_a_refund_revokes_the_license_and_stores_see_it()
    {
        $order = $this->order();
        $this->deliver($this->completed())->assertOk();
        $license = License::query()->firstOrFail();

        $this->deliver(['id' => 'evt_3', 'type' => 'charge.refunded', 'data' => ['object' => ['payment_intent' => 'pi_1', 'refunded' => true]]])->assertOk();

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame('refunded', $license->fresh()->status);
        $this->postJson('/api/marketplace/v1/licenses/check', ['key' => $license->key, 'instance_id' => 'store-a'])
            ->assertOk()->assertJsonPath('token.status', 'refunded');
    }

    public function test_a_refund_for_an_unknown_payment_is_ignored()
    {
        $this->deliver(['id' => 'evt_4', 'type' => 'charge.refunded', 'data' => ['object' => ['payment_intent' => 'pi_unknown', 'refunded' => true]]])->assertOk();

        $this->assertSame(0, Order::query()->count());
    }
}
