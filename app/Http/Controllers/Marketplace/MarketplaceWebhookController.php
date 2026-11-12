<?php

namespace App\Http\Controllers\Marketplace;

use App\Domains\Marketplace\MarketplaceCheckout;
use App\Domains\Payments\Gateway\InvalidWebhook;
use App\Domains\Payments\Gateways\Stripe\StripeApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stripe webhooks for the marketplace's own Stripe account (separate
 * endpoint and signing secret from any store's). Verified before anything
 * is stored; each event id is processed once.
 */
class MarketplaceWebhookController extends Controller
{
    public function __invoke(Request $request, MarketplaceCheckout $checkout): JsonResponse
    {
        $payload = $request->getContent();

        if (strlen($payload) > 262144) {
            return response()->json(['error' => 'Payload too large.'], 413);
        }

        try {
            $event = StripeApi::verifyWebhook($payload, array_map(fn ($v) => (string) ($v[0] ?? ''), $request->headers->all()), config('ecomodular.marketplace.server.stripe_webhook_secret'));
        } catch (InvalidWebhook $e) {
            Log::warning('Rejected marketplace webhook.', ['reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid webhook.'], 400);
        }

        $isNew = DB::table('payment_webhook_events')->insertOrIgnore([
            'provider' => 'marketplace-stripe',
            'event_id' => $event['id'],
            'type' => mb_substr($event['type'], 0, 120),
            'payload' => json_encode($event, JSON_THROW_ON_ERROR),
            'status' => 'processed',
            'attempts' => 1,
            'received_at' => now(),
            'processed_at' => now(),
        ]) > 0;

        if (! $isNew) {
            return response()->json(['status' => 'duplicate']);
        }

        /** @var array<string, mixed> $object */
        $object = $event['data']['object'];

        match ($event['type']) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $checkout->applySession($object),
            'refund.created', 'refund.updated' => $checkout->applyRefund($object),
            default => null,
        };

        return response()->json(['status' => 'accepted']);
    }
}
