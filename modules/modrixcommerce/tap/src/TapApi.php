<?php

namespace EcoModular\Tap;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Tap Charges API. The secret key is sent as a bearer token. Requests go
 * only to api.tap.company; the test or live key selects the account.
 *
 * @see https://developers.tap.company/reference/charges
 */
final class TapApi
{
    public function __construct(private readonly TapSettings $settings) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function post(string $path, array $body): array
    {
        return $this->send('post', $path, $body);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, $query);
    }

    /**
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        $this->get('/v2/charges', ['limit' => 1]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function send(string $method, string $path, array $body): array
    {
        if (! str_starts_with($path, '/v2/') || str_contains($path, '..')) {
            throw new GatewayException('Tap request path is not allowed.');
        }

        $secret = $this->settings->secret() ?? throw new GatewayException('Tap is not configured: add the secret key.');
        $request = Http::baseUrl(TapEndpoint::apiBase())
            ->withToken($secret)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->withoutRedirecting();

        try {
            $response = $method === 'get' ? $request->get($path, $body) : $request->post($path, $body);
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach Tap. Please try again.', retryable: $method === 'get');
        }

        return $this->decode($response, $method === 'get');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function decode(Response $response, bool $reading): array
    {
        $json = $response->json();

        if ($response->status() === 401) {
            throw new GatewayException('Tap rejected the secret key.');
        }

        if ($response->failed()) {
            $description = is_array($json) ? ($json['errors'][0]['description'] ?? $json['message'] ?? null) : null;
            $message = is_string($description) && $description !== '' ? $description : "the request failed (HTTP {$response->status()}).";

            throw new GatewayException(
                $this->redact('Tap: '.mb_substr($message, 0, 180)),
                $reading && (in_array($response->status(), [408, 429], true) || $response->serverError()),
            );
        }

        if (! is_array($json)) {
            throw new GatewayException('Tap returned an unreadable response.', $reading && $response->serverError());
        }

        return $json;
    }

    private function redact(string $message): string
    {
        $message = mb_substr($message, 0, 300);
        $secret = $this->settings->secret();

        return $secret === null ? $message : str_replace($secret, '[redacted]', $message);
    }
}
