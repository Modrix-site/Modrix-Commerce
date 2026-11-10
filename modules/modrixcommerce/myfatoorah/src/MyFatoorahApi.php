<?php

namespace EcoModular\MyFatoorah;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Communicates with MyFatoorah REST API v2.
 */
final class MyFatoorahApi
{
    public const TEST_BASE_URL = 'https://apitest.myfatoorah.com';

    public const PRODUCTION_BASE_URL = 'https://api.myfatoorah.com';

    public const TIMEOUT_SECONDS = 25;

    public function __construct(
        private readonly MyFatoorahSettings $settings,
    ) {}

    public function baseUrl(): string
    {
        return $this->settings->testMode() ? self::TEST_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    /**
     * Executes a payment session with MyFatoorah.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function executePayment(array $payload): array
    {
        $url = $this->baseUrl() . '/v2/ExecutePayment';

        $response = $this->sendRequest('POST', $url, $payload);

        if (! ($response['IsSuccess'] ?? false)) {
            $msg = $response['Message'] ?? 'Failed to execute MyFatoorah payment.';
            if (! empty($response['ValidationErrors']) && is_array($response['ValidationErrors'])) {
                $errDetails = collect($response['ValidationErrors'])->pluck('Error')->implode(', ');
                $msg .= ' (' . $errDetails . ')';
            }
            throw new GatewayException("MyFatoorah: {$msg}");
        }

        return $response;
    }

    /**
     * Retrieves the status of an invoice or payment from MyFatoorah.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function getPaymentStatus(string $key, string $keyType = 'InvoiceId'): array
    {
        $url = $this->baseUrl() . '/v2/GetPaymentStatus';

        $payload = [
            'Key' => $key,
            'KeyType' => $keyType,
        ];

        $response = $this->sendRequest('POST', $url, $payload);

        if (! ($response['IsSuccess'] ?? false)) {
            $msg = $response['Message'] ?? 'Failed to retrieve payment status from MyFatoorah.';
            throw new GatewayException("MyFatoorah: {$msg}");
        }

        return $response;
    }

    /**
     * Submits an online refund request to MyFatoorah.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function makeRefund(string $key, float $amount, string $currency, string $reason, string $keyType = 'InvoiceId'): array
    {
        $url = $this->baseUrl() . '/v2/MakeRefund';

        $payload = [
            'KeyType' => $keyType,
            'Key' => $key,
            'RefundModel' => [
                'Amount' => $amount,
                'Comment' => $reason,
                'CurrencyIso' => $currency,
            ],
        ];

        $response = $this->sendRequest('POST', $url, $payload);

        if (! ($response['IsSuccess'] ?? false)) {
            $msg = $response['Message'] ?? 'Failed to initiate MyFatoorah refund.';
            throw new GatewayException("MyFatoorah: {$msg}");
        }

        return $response;
    }

    /**
     * Tests connectivity and credential validity against MyFatoorah API.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function testConnection(): array
    {
        try {
            $apiKey = $this->settings->apiKey();
            if ($apiKey === null || trim($apiKey) === '') {
                throw new GatewayException('MyFatoorah API Key must be configured.');
            }

            $url = $this->baseUrl() . '/v2/InitiatePayment';
            $payload = [
                'InvoiceAmount' => 10,
                'CurrencyIso' => 'AED',
            ];

            $response = $this->sendRequest('POST', $url, $payload);

            if ($response['IsSuccess'] ?? false) {
                return [
                    'success' => true,
                    'message' => 'Connected successfully to MyFatoorah (' . ($this->settings->testMode() ? 'Test Environment' : 'Production') . ').',
                ];
            }

            return [
                'success' => false,
                'error' => $response['Message'] ?? 'MyFatoorah returned unsuccessful test connection response.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function sendRequest(string $method, string $url, array $body = []): array
    {
        $apiKey = $this->settings->apiKey();
        if ($apiKey === null || trim($apiKey) === '') {
            throw new GatewayException('MyFatoorah API Key is missing.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->send($method, $url, ['json' => $body]);
        } catch (ConnectionException $e) {
            Log::error('MyFatoorah connection error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Network error connecting to MyFatoorah service.', 0, $e);
        } catch (Throwable $e) {
            Log::error('MyFatoorah request error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Failed to communicate with MyFatoorah service: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            $decoded = $response->json();
            $msg = is_array($decoded) && isset($decoded['Message']) ? $decoded['Message'] : "HTTP {$status}";
            Log::warning('MyFatoorah API returned error', ['status' => $status, 'body' => $response->body()]);
            throw new GatewayException("MyFatoorah API error: {$msg}");
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new GatewayException('MyFatoorah response could not be parsed as JSON: ' . substr($response->body(), 0, 100));
        }

        return $decoded;
    }
}
