<?php

namespace EcoModular\Paypal;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PayPal REST API. Access tokens come from the client-credentials grant and
 * are cached until shortly before they expire. Requests go only to the
 * sandbox or live API host for the saved mode.
 *
 * @see https://developer.paypal.com/api/rest/authentication/
 */
final class PaypalApi
{
    public function __construct(private readonly PaypalSettings $settings) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function post(string $path, array $body, string $idempotencyKey): array
    {
        return $this->send('post', $path, $body, $idempotencyKey);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function get(string $path): array
    {
        return $this->send('get', $path, null, null);
    }

    /**
     * @param  array<string, mixed>  $event
     *
     * @throws GatewayException
     */
    public function signatureVerified(array $verification): bool
    {
        return ($verification['verification_status'] ?? null) === 'SUCCESS';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function verifySignature(array $body): array
    {
        return $this->send('post', '/v1/notifications/verify-webhook-signature', $body, null);
    }

    /**
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        $this->token(force: true);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function send(string $method, string $path, ?array $body, ?string $idempotencyKey): array
    {
        $request = Http::baseUrl($this->settings->apiBase())
            ->withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->withoutRedirecting()
            ->withHeaders(['Prefer' => 'return=representation']);

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $request = $request->withHeaders(['PayPal-Request-Id' => mb_substr($idempotencyKey, 0, 100)]);
        }

        try {
            $response = match (true) {
                $body === null => $request->get($path),
                $body === [] => $request->withBody('{}', 'application/json')->post($path),
                default => $request->post($path, $body),
            };
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach PayPal. Please try again.', retryable: $method === 'get');
        }

        return $this->decode($response);
    }

    /**
     * @throws GatewayException
     */
    private function token(bool $force = false): string
    {
        $clientId = $this->settings->clientId() ?? throw new GatewayException('PayPal is not configured: add the client ID.');
        $secret = $this->settings->clientSecret() ?? throw new GatewayException('PayPal is not configured: add the secret.');
        $cacheKey = 'paypal:token:'.($this->settings->sandbox() ? 'sandbox' : 'live').':'.hash('sha256', $clientId);

        if (! $force) {
            $cached = Cache::get($cacheKey);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        try {
            $response = Http::baseUrl($this->settings->apiBase())
                ->withBasicAuth($clientId, $secret)
                ->asForm()
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->withoutRedirecting()
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach PayPal. Please try again.', retryable: true);
        }

        $json = $this->decode($response);
        $token = $json['access_token'] ?? null;
        $expires = $json['expires_in'] ?? 0;

        if (! is_string($token) || $token === '' || ! is_int($expires)) {
            throw new GatewayException('PayPal did not return an access token.');
        }

        Cache::put($cacheKey, $token, now()->addSeconds(max(60, $expires - 120)));

        return $token;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function decode(Response $response): array
    {
        $json = $response->json();

        if ($response->status() === 401) {
            throw new GatewayException('PayPal rejected the client ID or secret.');
        }

        if ($response->failed()) {
            $issue = is_array($json) ? ($json['details'][0]['issue'] ?? $json['name'] ?? null) : null;
            $message = is_string($issue) ? $issue : "the request failed (HTTP {$response->status()}).";

            throw new GatewayException($this->redact('PayPal: '.mb_substr($message, 0, 180)), in_array($response->status(), [408, 429], true) || $response->serverError());
        }

        if (! is_array($json)) {
            throw new GatewayException('PayPal returned an unreadable response.', $response->serverError());
        }

        return $json;
    }

    private function redact(string $message): string
    {
        $message = mb_substr($message, 0, 300);

        foreach (array_filter([$this->settings->clientSecret(), $this->settings->clientId()]) as $secret) {
            $message = str_replace($secret, '[redacted]', $message);
        }

        return $message;
    }
}
