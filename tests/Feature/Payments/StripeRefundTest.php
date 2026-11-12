<?php

namespace Tests\Feature\Payments;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Payments\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\Support\StripeFixtures;
use Tests\TestCase;

class StripeRefundTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, StripeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        $this->configureStripe();
        Mail::fake();
        Http::preventStrayRequests();
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    private function paidStripeOrder(): array
    {
        $this->fakeStripeApi();
        [$order, $payment] = $this->placeStripeOrder('20.00', 2); // 40.00 + 5.00 shipping
        $this->postStripeWebhook($this->stripeEvent('checkout.session.completed', $this->completedSession($payment)))->assertOk();

        return [$order->fresh(), $payment->fresh()];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function refundObject(Payment $payment, int $amount, string $status = 'succeeded', array $overrides = []): array
    {
        return array_merge([
            'id' => 're_test_1',
            'object' => 'refund',
            'amount' => $amount,
            'currency' => 'usd',
            'payment_intent' => $payment->provider_payment_id,
            'status' => $status,
        ], $overrides);
    }

    public function test_admin_refund_is_issued_at_stripe_before_it_is_recorded()
    {
        [$order, $payment] = $this->paidStripeOrder();
        Http::fake(['api.stripe.com/v1/refunds' => Http::response($this->refundObject($payment, 1500, 'pending'))]);
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->userWithRole('order-manager'))
            ->post(route('admin.orders.refunds.store', $order), ['amount' => '15.00', 'reason' => 'Damaged', 'restock' => [$item->id => 1]])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.stripe.com/v1/refunds'
            && $r['payment_intent'] === $payment->provider_payment_id
            && $r['amount'] === 1500
            && $r->hasHeader('Idempotency-Key'));

        $refund = Refund::query()->firstOrFail();
        $this->assertSame('re_test_1', $refund->provider_reference);
        $this->assertSame(Refund::STATUS_PENDING, $refund->status);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->fresh()->payment_status);
        $this->assertSame(9, ProductVariant::query()->findOrFail($item->variant_id)->onHand());

        // Stripe later confirms it.
        $this->postStripeWebhook($this->stripeEvent('refund.updated', $this->refundObject($payment, 1500)))->assertOk();
        $this->assertSame(Refund::STATUS_COMPLETED, $refund->fresh()->status);
        $this->assertSame('15.0000', $order->fresh()->refunded_total);
    }

    public function test_a_refund_rejected_by_stripe_changes_nothing()
    {
        [$order] = $this->paidStripeOrder();
        Http::fake(['api.stripe.com/v1/refunds' => Http::response(['error' => ['message' => 'Charge has already been refunded.']], 400)]);

        $this->actingAs($this->owner())
            ->post(route('admin.orders.refunds.store', $order), ['amount' => '10.00', 'reason' => 'Test'])
            ->assertSessionHasErrors(['amount' => 'Stripe: Charge has already been refunded.']);

        $this->assertSame(0, Refund::query()->count());
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_a_double_submitted_refund_is_issued_and_recorded_once()
    {
        [$order, $payment] = $this->paidStripeOrder();
        Http::fake(['api.stripe.com/v1/refunds' => Http::response($this->refundObject($payment, 1000))]);
        $admin = $this->owner();

        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order), ['amount' => '10.00', 'reason' => 'Late']);
        // Same order state + amount → same idempotency key → Stripe returns the same refund.
        $order->fresh()->forceFill(['refunded_total' => '0'])->save();
        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order), ['amount' => '10.00', 'reason' => 'Late']);

        $this->assertSame(1, Refund::query()->count());
        $refundCalls = Http::recorded(fn (HttpRequest $r) => $r->url() === 'https://api.stripe.com/v1/refunds');
        $this->assertCount(2, $refundCalls);
        $this->assertCount(1, $refundCalls->map(fn ($pair) => $pair[0]->header('Idempotency-Key')[0])->unique());
    }

    public function test_a_refund_that_later_fails_at_stripe_is_reversed()
    {
        [$order, $payment] = $this->paidStripeOrder();
        Http::fake(['api.stripe.com/v1/refunds' => Http::response($this->refundObject($payment, 4500, 'pending'))]);
        $this->actingAs($this->owner())->post(route('admin.orders.refunds.store', $order), ['amount' => '45.00', 'reason' => 'Return']);
        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);

        $this->postStripeWebhook($this->stripeEvent('refund.failed', $this->refundObject($payment, 4500, 'failed', ['failure_reason' => 'expired_or_canceled_card'])))->assertOk();

        $order->refresh();
        $this->assertSame(Refund::STATUS_FAILED, Refund::query()->value('status'));
        $this->assertSame('0.0000', $order->refunded_total);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.refund_failed']);
    }

    public function test_refunds_made_in_the_stripe_dashboard_are_imported_once()
    {
        [$order, $payment] = $this->paidStripeOrder();
        $event = $this->stripeEvent('refund.created', $this->refundObject($payment, 500, 'succeeded', ['id' => 're_dashboard']));

        $this->postStripeWebhook($event)->assertOk();
        $this->postStripeWebhook($this->stripeEvent('refund.updated', $this->refundObject($payment, 500, 'succeeded', ['id' => 're_dashboard'])))->assertOk();

        $order->refresh();
        $this->assertSame(1, Refund::query()->count());
        $this->assertSame('5.0000', $order->refunded_total);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->payment_status);
        $this->assertNull(Refund::query()->value('actor_id'));
    }

    public function test_a_webhook_that_beats_the_admin_request_is_not_counted_twice()
    {
        [$order, $payment] = $this->paidStripeOrder();
        // Stripe's webhook for the refund is processed before our own request records it.
        Http::fake(['api.stripe.com/v1/refunds' => function () use ($payment) {
            $this->postStripeWebhook($this->stripeEvent('refund.created', $this->refundObject($payment, 1000)));

            return Http::response($this->refundObject($payment, 1000));
        }]);
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->owner())->post(route('admin.orders.refunds.store', $order), ['amount' => '10.00', 'reason' => 'Race', 'restock' => [$item->id => 1]]);

        $order->refresh();
        $refund = Refund::query()->sole();
        $this->assertSame('10.0000', $order->refunded_total);
        $this->assertSame('Race', $refund->reason);
        $this->assertNotNull($refund->actor_id);
        $this->assertSame(9, ProductVariant::query()->findOrFail($item->variant_id)->onHand());
    }
}
