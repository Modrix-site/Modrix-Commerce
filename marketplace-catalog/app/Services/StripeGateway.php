<?php

namespace Marketplace\Services;

use Illuminate\Support\Facades\Http;
use Marketplace\Models\Order;
use Marketplace\Models\Setting;
use RuntimeException;

/**
 * Stripe hosted Checkout and webhook signature checks. Keys come from
 * admin settings (stored encrypted).
 */
class StripeGateway
{
    /** Webhook timestamps older than this are refused (replay protection). */
    public const TOLERANCE = 300;

    public function configured(): bool
    {
        return str_starts_with((string) Setting::get('stripe_secret', ''), 'sk_');
    }

    /**
     * Creates a Checkout session for the order and returns Stripe's URL.
     */
    public function createSession(Order $order, string $successUrl, string $cancelUrl): string
    {
        $order->loadMissing('package', 'user');
        $amount = (int) round(((float) $order->amount) * 100);

        $response = Http::asForm()
            ->withBasicAuth((string) Setting::get('stripe_secret', ''), '')
            ->timeout(20)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'customer_email' => $order->user->email,
                'client_reference_id' => (string) $order->id,
                'metadata' => ['order_id' => (string) $order->id, 'package' => $order->package->identifier],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($order->currency),
                        'unit_amount' => $amount,
                        'product_data' => ['name' => $order->package->name],
                    ],
                ]],
            ]);

        if (! $response->successful() || ! is_string($response->json('url'))) {
            throw new RuntimeException('Stripe did not create a checkout session: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
        }

        $order->forceFill(['stripe_session' => (string) $response->json('id')])->save();

        return (string) $response->json('url');
    }

    /**
     * Checks the Stripe-Signature header and returns the decoded event.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidWebhook
     */
    public function verify(string $payload, ?string $header): array
    {
        $secret = (string) Setting::get('stripe_webhook_secret', '');

        if ($secret === '' || $header === null) {
            throw new InvalidWebhook('Missing webhook secret or signature.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($name === 't') {
                $timestamp = (int) $value;
            } elseif ($name === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || abs(time() - $timestamp) > self::TOLERANCE) {
            throw new InvalidWebhook('The webhook timestamp is outside the tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $event = json_decode($payload, true);

                if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)) {
                    throw new InvalidWebhook('The webhook payload is not an event.');
                }

                return $event;
            }
        }

        throw new InvalidWebhook('The webhook signature does not match.');
    }
}
