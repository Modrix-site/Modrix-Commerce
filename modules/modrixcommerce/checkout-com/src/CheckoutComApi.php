<?php

namespace EcoModular\CheckoutCom;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Checkout.com Payments API. Requests go only to the merchant's validated
 * API host. The secret key is a bearer token and is stripped from errors.
 */
final class CheckoutComApi
{
    public function __construct(private readonly CheckoutComSettings $settings) {}

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
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function send(string $method, string $path, ?array $body, ?string $idempotencyKey): array
    {
        $base = $this->settings->apiBase() ?? throw new GatewayException('Checkout.com is not configured: add the API base URL.');
        $key = $this->settings->secretKey() ?? throw new GatewayException('Checkout.com is not configured: add the secret key.');

        $request = Http::baseUrl($base)
            ->withToken($key)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->withoutRedirecting();

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $request = $request->withHeaders(['Cko-Idempotency-Key' => mb_substr($idempotencyKey, 0, 255)]);
        }

        try {
            $response = $body === null ? $request->get($path) : $request->post($path, $body);
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach Checkout.com. Please try again.', retryable: $method === 'get');
        }

        $json = $response->json();

        if ($response->status() === 404 && is_array($json)) {
            return $json + ['_http_status' => 404];
        }

        if ($response->failed()) {
            throw $this->error($response->status(), is_array($json) ? $json : []);
        }

        if (! is_array($json)) {
            throw new GatewayException('Checkout.com returned an unreadable response.', $response->serverError());
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public function error(int $status, array $json): GatewayException
    {
        $codes = $json['error_codes'] ?? null;
        $message = is_array($codes) && $codes !== []
            ? implode(', ', array_map(strval(...), $codes))
            : (is_string($json['error_type'] ?? null) ? $json['error_type'] : "the request failed (HTTP {$status}).");

        return new GatewayException($this->redact('Checkout.com: '.mb_substr($message, 0, 240)), in_array($status, [408, 429], true) || $status >= 500);
    }

    public function redact(string $message): string
    {
        $message = mb_substr($message, 0, 300);

        foreach (array_filter([$this->settings->secretKey(), $this->settings->publicKey(), $this->settings->webhookSecret()]) as $secret) {
            $message = str_replace($secret, '[redacted]', $message);
        }

        return (string) preg_replace('/\b(sk|pk)_(sbox_)?[A-Za-z0-9_-]+/', '[redacted]', $message);
    }
}
