<?php

namespace EcoModular\Tabby;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Tabby REST client. Requests go only to the fixed API host of the
 * store's market; redirects are not followed and keys never appear in
 * errors or logs.
 */
final class TabbyApi
{
    public function __construct(private readonly TabbySettings $settings) {}

    /**
     * @param  array<string, mixed>|null  $body
     * @param  bool  $retry  only for requests that are safe to repeat (reads, or writes with a reference_id)
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function request(string $method, string $path, ?array $body = null, bool $retry = false, int $timeout = 20): array
    {
        $response = $this->send($method, $path, $body, $retry, $timeout);

        if ($response->failed()) {
            throw self::error($response);
        }

        $json = $response->json();

        return is_array($json) ? $json : throw new GatewayException('Tabby returned an unreadable response.');
    }

    /**
     * Like request(), but returns the raw response (used where a 4xx is an answer, not an error).
     *
     * @param  array<string, mixed>|null  $body
     *
     * @throws GatewayException
     */
    public function send(string $method, string $path, ?array $body = null, bool $retry = false, int $timeout = 20): Response
    {
        $key = $this->settings->secretKey() ?? throw new GatewayException('Tabby is not configured: add the secret key.');
        $market = $this->settings->market() ?? throw new GatewayException('Tabby supports stores priced in AED or SAR only.');

        $request = Http::baseUrl($market->apiBase())
            ->withToken($key)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout($timeout)
            ->withoutRedirecting();

        if ($this->settings->merchantCode() !== null) {
            $request = $request->withHeaders(['X-Merchant-Code' => $this->settings->merchantCode()]);
        }

        if ($retry) {
            $request = $request->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);
        }

        try {
            return $body === null ? $request->{$method}($path) : $request->{$method}($path, $body);
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach Tabby. Please try again.', retryable: true);
        }
    }

    public static function error(Response $response): GatewayException
    {
        $message = $response->json('error') ?? $response->json('message') ?? $response->json('errors.0.message');
        $message = is_string($message) && $message !== '' ? $message : match (true) {
            in_array($response->status(), [401, 403], true) => 'the secret key or merchant code was rejected.',
            $response->status() === 404 => 'the payment was not found.',
            $response->status() === 429 => 'too many requests; try again shortly.',
            default => "the request failed (HTTP {$response->status()}).",
        };
        // Never echo anything that looks like a key back to the UI or logs.
        $message = (string) preg_replace('/\b(sk|pk)_(test_)?[A-Za-z0-9-]+/', '[redacted]', mb_substr($message, 0, 300));

        return new GatewayException("Tabby: {$message}", $response->status() === 429 || $response->serverError());
    }
}
