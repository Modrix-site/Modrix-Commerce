<?php

namespace EcoModular\NGenius;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Communicates with Network International (N-Genius) Online Payment Gateway APIs.
 */
final class NGeniusApi
{
    public const SANDBOX_URL = 'https://api-gateway.sandbox.ngenius-payments.com';

    public const PRODUCTION_URL = 'https://api-gateway.ngenius-payments.com';

    public const TIMEOUT_SECONDS = 25;

    public function __construct(private readonly NGeniusSettings $settings) {}

    public function baseUrl(): string
    {
        return $this->settings->testMode() ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    /**
     * Authenticates using the service account API key and retrieves an access token.
     * Caches token in memory/cache to avoid redundant round-trips.
     *
     * @throws GatewayException
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        $apiKey = $this->settings->apiKey();
        if ($apiKey === null || trim($apiKey) === '') {
            throw new GatewayException('N-Genius API key is not configured.');
        }

        $cacheKey = 'ngenius_access_token_'.md5($apiKey.($this->settings->testMode() ? '_test' : '_live'));

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && ! empty($cached)) {
                return $cached;
            }
        }

        $url = $this->baseUrl().'/identity/auth/access-token';

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Authorization' => 'Basic '.$apiKey,
                    'Content-Type' => 'application/vnd.ni-identity.v1+json',
                    'Accept' => 'application/vnd.ni-identity.v1+json',
                ])
                ->post($url, [
                    'realmName' => 'ni',
                ]);
        } catch (ConnectionException $e) {
            Log::error('N-Genius auth connection failure', ['error' => $e->getMessage()]);
            throw new GatewayException('Network error connecting to N-Genius identity service.', 0, $e);
        } catch (Throwable $e) {
            Log::error('N-Genius auth unexpected error', ['error' => $e->getMessage()]);
            throw new GatewayException('Failed to communicate with N-Genius authentication service.', 0, $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->json();
            $msg = $body['message'] ?? $body['error_description'] ?? 'Status '.$status;
            Log::warning('N-Genius auth request rejected', ['status' => $status, 'body' => $body]);
            throw new GatewayException("N-Genius authentication failed ({$status}): {$msg}");
        }

        $data = $response->json();
        $token = $data['access_token'] ?? null;

        if (! is_string($token) || empty($token)) {
            throw new GatewayException('N-Genius did not return a valid access_token.');
        }

        $expiresIn = (int) ($data['expires_in'] ?? 300);
        $ttl = max(60, $expiresIn - 60);

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * Creates an order at N-Genius to generate a payment URL.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function createOrder(array $body): array
    {
        $outletId = $this->settings->outletId();
        if ($outletId === null) {
            throw new GatewayException('N-Genius Outlet ID is missing.');
        }

        return $this->request('POST', "/transactions/outlets/{$outletId}/orders", $body);
    }

    /**
     * Queries order details and embedded payment states.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function getOrder(string $orderReference): array
    {
        $outletId = $this->settings->outletId();
        if ($outletId === null) {
            throw new GatewayException('N-Genius Outlet ID is missing.');
        }

        return $this->request('GET', "/transactions/outlets/{$outletId}/orders/{$orderReference}");
    }

    /**
     * Executes an API refund against a captured payment.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function refund(string $orderReference, string $paymentReference, array $body): array
    {
        $outletId = $this->settings->outletId();
        if ($outletId === null) {
            throw new GatewayException('N-Genius Outlet ID is missing.');
        }

        return $this->request(
            'POST',
            "/transactions/outlets/{$outletId}/orders/{$orderReference}/payments/{$paymentReference}/refund",
            $body
        );
    }

    /**
     * Tests API credentials against N-Genius identity endpoint.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function testConnection(): array
    {
        try {
            $this->getAccessToken(forceRefresh: true);

            return [
                'success' => true,
                'message' => 'Connected successfully to Network International N-Genius ('.($this->settings->testMode() ? 'Sandbox' : 'Live').').',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function request(string $method, string $path, ?array $data = null, bool $retryOnAuth = true): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl().$path;

        try {
            $req = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/vnd.ni-payment.v2+json',
                    'Accept' => 'application/vnd.ni-payment.v2+json',
                ]);

            $response = match (strtoupper($method)) {
                'POST' => $req->post($url, $data ?? []),
                'GET' => $req->get($url, $data ?? []),
                default => throw new GatewayException("Unsupported HTTP method {$method}"),
            };
        } catch (ConnectionException $e) {
            Log::error('N-Genius connection error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new GatewayException('Network error connecting to N-Genius: '.$e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            Log::error('N-Genius client error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new GatewayException('Error communicating with N-Genius: '.$e->getMessage(), 0, $e);
        }

        // Token may have expired prematurely; retry once with a freshly requested token
        if ($response->status() === 401 && $retryOnAuth) {
            $this->getAccessToken(forceRefresh: true);

            return $this->request($method, $path, $data, false);
        }

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->json();
            $message = $body['message'] ?? $body['errors'][0]['message'] ?? ('HTTP '.$status.' error');
            Log::warning('N-Genius API error response', ['path' => $path, 'status' => $status, 'body' => $body]);
            throw new GatewayException("N-Genius API request failed ({$status}): {$message}");
        }

        return (array) $response->json();
    }
}
