<?php

namespace App\Http\Controllers;

use App\Domains\Payments\Gateway\InvalidWebhook;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Payments\WebhookInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives payment provider webhooks. Registered outside the `web` group:
 * no session, no CSRF token (the provider's signature authenticates it).
 */
class PaymentWebhookController extends Controller
{
    /** Provider events are small; anything larger is not a genuine delivery. */
    private const MAX_PAYLOAD_BYTES = 262144;

    public function __invoke(Request $request, string $provider, PaymentMethods $methods, WebhookInbox $inbox): JsonResponse
    {
        $gateway = $methods->gatewayForProvider($provider);
        abort_if($gateway === null, 404);

        $payload = $request->getContent();

        if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            return response()->json(['error' => 'Payload too large.'], 413);
        }

        $headers = array_map(fn ($values) => (string) ($values[0] ?? ''), $request->headers->all());

        try {
            $queued = $inbox->receive($gateway, $payload, $headers);
        } catch (InvalidWebhook $e) {
            // Never log the payload or signature of a rejected delivery.
            Log::warning('Rejected payment webhook.', ['provider' => $provider, 'reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid webhook.'], 400);
        }

        return response()->json(['status' => $queued ? 'accepted' : 'duplicate']);
    }
}
