<?php

namespace EcoModular\Instamojo;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Communicates with Instamojo REST API v1.1.
 */
final class InstamojoApi
{
    public const TEST_BASE_URL = 'https://test.instamojo.com/api/1.1';

    public const PRODUCTION_BASE_URL = 'https://www.instamojo.com/api/1.1';

    public const TIMEOUT_SECONDS = 25;

    public function __construct(private readonly InstamojoSettings $settings) {}

    public function baseUrl(): string
    {
        return $this->settings->testMode() ? self::TEST_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    /**
     * Creates a new Payment Request at Instamojo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function createPaymentRequest(array $data): array
    {
        return $this->request('POST', '/payment-requests/', $data);
    }

    /**
     * Retrieves a Payment Request by its ID.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function getPaymentRequest(string $paymentRequestId): array
    {
        return $this->request('GET', "/payment-requests/{$paymentRequestId}/");
    }

    /**
     * Submits an API refund for a completed payment.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function refund(string $paymentId, string $type, string $body, string $refundAmount): array
    {
        $params = [
            'payment_id' => $paymentId,
            'type' => $type,
            'body' => $body,
            'refund_amount' => $refundAmount,
        ];

        return $this->request('POST', '/refunds/', $params);
    }

    /**
     * Tests API credentials against Instamojo endpoint.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function testConnection(): array
    {
        try {
            $apiKey = $this->settings->apiKey();
            $authToken = $this->settings->authToken();

            if ($apiKey === null || $authToken === null) {
                throw new GatewayException('Instamojo API Key and Auth Token must be configured.');
            }

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'X-Api-Key' => $apiKey,
                    'X-Auth-Token' => $authToken,
                ])
                ->get($this->baseUrl().'/payment-requests/?limit=1');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connected successfully to Instamojo ('.($this->settings->testMode() ? 'Test' : 'Live').').',
                ];
            }

            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'success' => false,
                    'error' => 'Invalid Instamojo API Key or Auth Token.',
                ];
            }

            return [
                'success' => false,
                'error' => 'Instamojo returned HTTP '.$response->status(),
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
        $authToken = $this->settings->authToken();

        if ($apiKey === null || $authToken === null) {
            throw new GatewayException('Instamojo credentials are not configured.');
        }

        $url = $this->baseUrl().$path;

        try {
            $client = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'X-Api-Key' => $apiKey,
                    'X-Auth-Token' => $authToken,
                ]);

            $response = match (strtoupper($method)) {
                'POST' => $client->post($url, $data ?? []),
                'GET' => $client->get($url, $data ?? []),
                default => throw new GatewayException("Unsupported HTTP method {$method}"),
            };
        } catch (ConnectionException $e) {
            Log::error('Instamojo connection failure', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Network error connecting to Instamojo API.', 0, $e);
        } catch (Throwable $e) {
            Log::error('Instamojo unexpected error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Failed to communicate with Instamojo: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->json();
            $msg = $body['message'] ?? (is_array($body) ? json_encode($body) : ('HTTP '.$status.' error'));
            Log::warning('Instamojo API error response', ['path' => $path, 'status' => $status, 'body' => $body]);
            throw new GatewayException("Instamojo API request failed ({$status}): {$msg}");
        }

        return (array) $response->json();
    }
}
