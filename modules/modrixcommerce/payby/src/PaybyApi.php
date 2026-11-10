<?php

namespace EcoModular\PayBy;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Communicates with PayBy (Astra Tech) acquiring gateway APIs.
 */
final class PaybyApi
{
    public const UAT_URL = 'https://uat.test2pay.com';

    public const PRODUCTION_URL = 'https://api.payby.com';

    public const TIMEOUT_SECONDS = 25;

    public function __construct(
        private readonly PaybySettings $settings,
        private readonly PaybySignature $signature,
    ) {}

    public function baseUrl(): string
    {
        return $this->settings->testMode() ? self::UAT_URL : self::PRODUCTION_URL;
    }

    /**
     * Creates an acquiring order at PayBy.
     *
     * @param  array<string, mixed>  $bizContent
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function placeOrder(array $bizContent): array
    {
        $payload = [
            'requestTime' => (int) round(microtime(true) * 1000),
            'bizContent' => $bizContent,
        ];

        return $this->post('/sgs/api/acquire2/placeOrder', $payload);
    }

    /**
     * Queries order status and details by merchant order number or PayBy order number.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function queryOrder(string $merchantOrderNo, ?string $orderNo = null): array
    {
        $bizContent = [
            'merchantOrderNo' => $merchantOrderNo,
        ];

        if ($orderNo !== null && $orderNo !== '') {
            $bizContent['orderNo'] = $orderNo;
        }

        $payload = [
            'requestTime' => (int) round(microtime(true) * 1000),
            'bizContent' => $bizContent,
        ];

        return $this->post('/sgs/api/acquire2/order/query', $payload);
    }

    /**
     * Submits an API refund for a settled order.
     *
     * @param  array<string, mixed>  $bizContent
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function refund(array $bizContent): array
    {
        $payload = [
            'requestTime' => (int) round(microtime(true) * 1000),
            'bizContent' => $bizContent,
        ];

        return $this->post('/sgs/api/acquire2/refund/placeOrder', $payload);
    }

    /**
     * Probes gateway connection using merchant credentials.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function testConnection(): array
    {
        try {
            $partnerId = $this->settings->partnerId();
            if ($partnerId === null || ! $this->settings->hasPrivateKey()) {
                throw new GatewayException('PayBy Partner ID and RSA Private Key must be configured.');
            }

            // Probe by querying a dummy order number
            $response = $this->queryOrder('PROBE_TEST_' . time());

            if (isset($response['head']['respCode'])) {
                return [
                    'success' => true,
                    'message' => 'Connected successfully to PayBy (' . ($this->settings->testMode() ? 'UAT' : 'Production') . ').',
                ];
            }

            return [
                'success' => false,
                'error' => 'PayBy did not return a valid response structure.',
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
    private function post(string $path, array $payload): array
    {
        $partnerId = $this->settings->partnerId();
        $privateKey = $this->settings->privateKey();

        if ($partnerId === null || $privateKey === null) {
            throw new GatewayException('PayBy credentials are not configured.');
        }

        $bodyJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($bodyJson === false) {
            throw new GatewayException('Could not encode PayBy request payload to JSON.');
        }

        try {
            $sign = $this->signature->sign($bodyJson, $privateKey);
        } catch (Throwable $e) {
            throw new GatewayException('Failed to sign PayBy request: ' . $e->getMessage(), 0, $e);
        }

        $url = $this->baseUrl() . $path;

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Partner-Id' => $partnerId,
                    'sign' => $sign,
                    'Content-Type' => 'application/json',
                    'Content-Language' => 'en',
                ])
                ->withBody($bodyJson, 'application/json')
                ->post($url);
        } catch (ConnectionException $e) {
            Log::error('PayBy connection error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Network error connecting to PayBy API.', 0, $e);
        } catch (Throwable $e) {
            Log::error('PayBy unexpected request error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Error communicating with PayBy: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->json();
            $msg = $body['head']['respMsg'] ?? ('HTTP ' . $status . ' error');
            Log::warning('PayBy API error response', ['path' => $path, 'status' => $status, 'body' => $body]);
            throw new GatewayException("PayBy API error ({$status}): {$msg}");
        }

        return (array) $response->json();
    }
}
