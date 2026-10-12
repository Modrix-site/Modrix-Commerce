<?php

namespace App\Domains\Payments\Gateways\Stripe;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Gateway\InvalidWebhook;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Stripe REST client and webhook signature check (ADR-025), shared
 * by the store's Stripe gateway and the marketplace checkout. Each caller
 * passes its own credentials: the two never share an account.
 */
final class StripeApi
{
    /** Pinned so payload shapes do not change underneath us. */
    public const API_VERSION = '2024-06-20';

    /** Maximum accepted age of a webhook signature timestamp, in seconds. */
    public const SIGNATURE_TOLERANCE = 300;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public static function request(string $secretKey, string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        $request = Http::baseUrl((string) config('services.stripe.base_url', 'https://api.stripe.com'))
            ->withToken($secretKey)
            ->withHeaders(['Stripe-Version' => self::API_VERSION])
            ->acceptJson()
            ->asForm()
            ->connectTimeout(5)
            ->timeout(20)
            // Safe to retry: creating requests carry an idempotency key.
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        try {
            $response = $request->{$method}($path, $params);
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach Stripe. Please try again.', retryable: true);
        }

        if ($response->failed()) {
            throw self::error($response);
        }

        $body = $response->json();

        return is_array($body) ? $body : throw new GatewayException('Stripe returned an unreadable response.');
    }

    /**
     * Verifies a Stripe-Signature header (HMAC-SHA256 over "timestamp.payload")
     * and returns the decoded event.
     *
     * @param  array<string, string>  $headers  lower-cased header names
     * @return array<string, mixed>
     *
     * @throws InvalidWebhook
     */
    public static function verifyWebhook(string $payload, array $headers, ?string $secret): array
    {
        if ($secret === null || $secret === '') {
            throw new InvalidWebhook('Stripe webhooks are not configured.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $headers['stripe-signature'] ?? '') as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new InvalidWebhook('Missing or malformed Stripe-Signature header.');
        }

        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE) {
            throw new InvalidWebhook('Stripe signature timestamp is outside the tolerance window.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        if (array_filter($signatures, fn (string $signature) => hash_equals($expected, $signature)) === []) {
            throw new InvalidWebhook('Stripe signature does not match.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null) || ! is_array($event['data']['object'] ?? null)) {
            throw new InvalidWebhook('Stripe event payload is malformed.');
        }

        return $event;
    }

    private static function error(Response $response): GatewayException
    {
        $message = (string) ($response->json('error.message') ?? 'Stripe rejected the request.');
        // Never echo anything that looks like a key back to the UI or logs.
        $message = (string) preg_replace('/\b(sk|rk|pk|whsec)_[A-Za-z0-9_]+/', '[redacted]', $message);

        return new GatewayException("Stripe: {$message}", $response->status() === 429 || $response->serverError());
    }
}
