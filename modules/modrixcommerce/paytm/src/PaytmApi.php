<?php

namespace EcoModular\Paytm;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Communicates with Paytm Payment Gateway (PG) REST API v1/v3.
 */
final class PaytmApi
{
    public const STAGING_URL = 'https://securegw-stage.paytm.in';

    public const PRODUCTION_URL = 'https://securegw.paytm.in';

    public const TIMEOUT_SECONDS = 25;

    public function __construct(private readonly PaytmSettings $settings) {}

    public function baseUrl(): string
    {
        return $this->settings->testMode() ? self::STAGING_URL : self::PRODUCTION_URL;
    }

    public function showPaymentPageUrl(string $orderId): string
    {
        $mid = $this->settings->mid();

        return $this->baseUrl() . "/theia/api/v1/showPaymentPage?mid={$mid}&orderId={$orderId}";
    }

    /**
     * Calls Paytm Initiate Transaction API to generate a txnToken.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function initiateTransaction(string $orderId, array $body): array
    {
        $mid = $this->settings->mid();
        $key = $this->settings->merchantKey();

        if ($mid === null || $key === null) {
            throw new GatewayException('Paytm MID or Merchant Key is missing.');
        }

        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
        if ($bodyJson === false) {
            throw new GatewayException('Could not encode Paytm transaction payload to JSON.');
        }

        $signature = PaytmChecksum::generateSignature($bodyJson, $key);

        $payload = [
            'body' => $body,
            'head' => [
                'signature' => $signature,
            ],
        ];

        $url = $this->baseUrl() . "/theia/api/v1/initiateTransaction?mid={$mid}&orderId={$orderId}";

        return $this->post($url, $payload);
    }

    /**
     * Queries transaction status from Paytm.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function getTransactionStatus(string $orderId): array
    {
        $mid = $this->settings->mid();
        $key = $this->settings->merchantKey();

        if ($mid === null || $key === null) {
            throw new GatewayException('Paytm MID or Merchant Key is missing.');
        }

        $body = [
            'mid' => $mid,
            'orderId' => $orderId,
        ];

        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
        if ($bodyJson === false) {
            throw new GatewayException('Could not encode status request body.');
        }

        $signature = PaytmChecksum::generateSignature($bodyJson, $key);

        $payload = [
            'body' => $body,
            'head' => [
                'signature' => $signature,
            ],
        ];

        $url = $this->baseUrl() . '/v3/order/status';

        return $this->post($url, $payload);
    }

    /**
     * Submits an API refund request to Paytm.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function refund(string $orderId, string $txnId, string $refId, string $refundAmount): array
    {
        $mid = $this->settings->mid();
        $key = $this->settings->merchantKey();

        if ($mid === null || $key === null) {
            throw new GatewayException('Paytm MID or Merchant Key is missing.');
        }

        $body = [
            'mid' => $mid,
            'txnType' => 'REFUND',
            'orderId' => $orderId,
            'txnId' => $txnId,
            'refId' => $refId,
            'refundAmount' => $refundAmount,
        ];

        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
        if ($bodyJson === false) {
            throw new GatewayException('Could not encode refund request body.');
        }

        $signature = PaytmChecksum::generateSignature($bodyJson, $key);

        $payload = [
            'body' => $body,
            'head' => [
                'signature' => $signature,
            ],
        ];

        $url = $this->baseUrl() . '/refund/apply';

        return $this->post($url, $payload);
    }

    /**
     * Probes Paytm gateway connection using merchant credentials.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function testConnection(): array
    {
        try {
            $mid = $this->settings->mid();
            if ($mid === null || ! $this->settings->hasMerchantKey()) {
                throw new GatewayException('Paytm MID and Merchant Key must be configured.');
            }

            // Probe status of a dummy test transaction
            $response = $this->getTransactionStatus('TEST_PROBE_' . time());
            $resultStatus = $response['body']['resultInfo']['resultStatus'] ?? null;

            if ($resultStatus !== null) {
                return [
                    'success' => true,
                    'message' => 'Connected successfully to Paytm Gateway (' . ($this->settings->testMode() ? 'Staging' : 'Production') . ').',
                ];
            }

            return [
                'success' => false,
                'error' => 'Paytm did not return a valid resultInfo structure.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function post(string $url, array $payload): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::error('Paytm connection failed', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Network error connecting to Paytm API.', 0, $e);
        } catch (Throwable $e) {
            Log::error('Paytm unexpected client error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Failed to communicate with Paytm API: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->json();
            $msg = $body['body']['resultInfo']['resultMsg'] ?? ('HTTP ' . $status . ' error');
            Log::warning('Paytm API returned error response', ['url' => $url, 'status' => $status, 'body' => $body]);
            throw new GatewayException("Paytm API error ({$status}): {$msg}");
        }

        return (array) $response->json();
    }
}
