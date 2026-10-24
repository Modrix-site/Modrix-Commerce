<?php

namespace Marketplace\Http\Controllers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Marketplace\Mail\LicenseIssued;
use Marketplace\Models\Order;
use Marketplace\Models\WebhookEvent;
use Marketplace\Services\InvalidWebhook;
use Marketplace\Services\Licensing;
use Marketplace\Services\StripeGateway;

/**
 * POST /stripe/webhook. Each event is handled once (webhook_events); repeats
 * and events about unknown orders are acknowledged without side effects.
 */
class StripeWebhookController
{
    public function __invoke(Request $request, StripeGateway $stripe, Licensing $licensing): JsonResponse
    {
        try {
            $event = $stripe->verify($request->getContent(), $request->header('Stripe-Signature'));
        } catch (InvalidWebhook $e) {
            return response()->json(['error' => 'invalid_signature', 'message' => $e->getMessage()], 400);
        }

        try {
            $record = WebhookEvent::query()->create(['provider' => 'stripe', 'event_id' => $event['id'], 'type' => $event['type']]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $object = (array) ($event['data']['object'] ?? []);
        $mail = null;

        DB::transaction(function () use ($event, $object, $licensing, &$mail) {
            match ($event['type']) {
                'checkout.session.completed' => $mail = $this->completed($object, $licensing),
                'checkout.session.expired' => Order::query()->where('stripe_session', $object['id'] ?? '')->where('status', 'pending')->update(['status' => 'expired']),
                'charge.refunded' => $this->refunded($object),
                default => null,
            };
        });

        $record->forceFill(['processed_at' => now()])->save();

        if ($mail instanceof Order) {
            Mail::to($mail->user->email)->queue(new LicenseIssued($mail));
        }

        return response()->json(['received' => true]);
    }

    /** Marks the order paid and issues (or returns) its license. */
    private function completed(array $session, Licensing $licensing): ?Order
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            return null;
        }

        $order = Order::query()->with(['user', 'package'])->where('stripe_session', $session['id'] ?? '')->lockForUpdate()->first();

        if (! $order instanceof Order || $order->status === 'paid') {
            return null;
        }

        $license = $licensing->issue($order->user, $order->package, 'paid');
        $license->forceFill(['type' => 'paid', 'status' => 'active'])->save();
        $order->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
            'license_id' => $license->id,
            'stripe_payment_intent' => is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null,
        ])->save();

        return $order;
    }

    /** A refunded payment revokes its license; the store's next check sees it. */
    private function refunded(array $charge): void
    {
        $intent = $charge['payment_intent'] ?? null;

        if (! is_string($intent) || ($charge['refunded'] ?? false) !== true) {
            return;
        }

        $order = Order::query()->where('stripe_payment_intent', $intent)->lockForUpdate()->first();

        if (! $order instanceof Order) {
            return;
        }

        $order->forceFill(['status' => 'refunded'])->save();
        $order->license?->forceFill(['status' => 'refunded'])->save();
    }
}
