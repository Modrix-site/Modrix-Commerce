<?php

namespace EcoModular\Ziina;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Communicates with Ziina REST API v2 (`https://api-v2.ziina.com/api`).
 */
final class ZiinaApi
{
    public const BASE_URL = 'https://api-v2.ziina.com/api';

    public const TIMEOUT_SECONDS = 25;

    public function __construct(private readonly ZiinaSettings $settings) {}

    /**
     * Creates a new Payment Intent at Ziina.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function createPaymentIntent(array $payload): array
    {
        return $this->request('POST', '/payment_intent', $payload);
    }

    /**
     * Retrieves the status and details of a Payment Intent.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function getPaymentIntent(string $id): array
    {
        return $this->request('GET', "/payment_intent/{$id}");
    }

    /**
     * Submits a full or partial refund for a Payment Intent.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function refund(string $paymentIntentId, int $amountMinor, string $currency): array
    {
        $payload = [
            'payment_intent_id' => $paymentIntentId,
            'amount' => $amountMinor,
            'currency_code' => strtoupper($currency),
        ];

        return $this->request('POST', '/refund', $payload);
    }

    /**
     * Tests API token validity against Ziina endpoints.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function testConnection(): array
    {
        try {
            $apiKey = $this->settings->apiKey();
            if ($apiKey === null || trim($apiKey) === '') {
                throw new GatewayException('Ziina API token is not configured.');
            }

            // Probe by querying payment intents list or a test probe
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withToken($apiKey)
                ->acceptJson()
                ->get(self::BASE_URL.'/payment_intent');

            // 200 OK or 404 (no intents yet) with valid auth confirms connection
            if ($response->successful() || $response->status() === 404) {
                return [
                    'success' => true,
                    'message' => 'Connected successfully to Ziina ('.($this->settings->testMode() ? 'Test' : 'Live').').',
                ];
            }

            if ($response->status() === 401) {
                return [
                    'success' => false,
                    'error' => 'Invalid Ziina API token. Authentication failed.',
                ];
            }

            return [
                'success' => false,
                'error' => 'Ziina returned HTTP '.$response->status(),
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
    private function request(string $method, string $path, ?array $data = null): array
    {
        $apiKey = $this->settings->apiKey();
        if ($apiKey === null || trim($apiKey) === '') {
            throw new GatewayException('Ziina API token is not configured.');
        }

        $url = self::BASE_URL.$path;

        try {
            $req = Http::timeout(self::TIMEOUT_SECONDS)
                ->withToken($apiKey)
                ->acceptJson();

            $response = match (strtoupper($method)) {
                'POST' => $req->post($url, $data ?? []),
                'GET' => $req->get($url, $data ?? []),
                default => throw new GatewayException("Unsupported HTTP method {$method}"),
            };
        } catch (ConnectionException $e) {
            Log::error('Ziina connection failure', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Network error connecting to Ziina API.', 0, $e);
        } catch (Throwable $e) {
            Log::error('Ziina unexpected request error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Error communicating with Ziina: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->json();
            $msg = $body['message'] ?? $body['error'] ?? ('HTTP '.$status.' error');
            Log::warning('Ziina API error response', ['path' => $path, 'status' => $status, 'body' => $body]);
            throw new GatewayException("Ziina API error ({$status}): {$msg}");
        }

        return (array) $response->json();
    }
}
