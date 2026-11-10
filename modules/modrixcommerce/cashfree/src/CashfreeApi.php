<?php

namespace EcoModular\Cashfree;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Communicates with Cashfree PG REST APIs (version 2023-08-01).
 */
final class CashfreeApi
{
    public const API_VERSION = '2023-08-01';

    public const SANDBOX_URL = 'https://sandbox.cashfree.com/pg';

    public const PRODUCTION_URL = 'https://api.cashfree.com/pg';

    public function __construct(private readonly CashfreeSettings $settings) {}

    public function baseUrl(): string
    {
        return $this->settings->testMode() ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    public function sdkMode(): string
    {
        return $this->settings->testMode() ? 'sandbox' : 'production';
    }

    /**
     * Creates an order at Cashfree to obtain a payment_session_id.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function createOrder(array $body): array
    {
        return $this->post('/orders', $body);
    }

    /**
     * Queries order status from Cashfree by order ID.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function getOrder(string $orderId): array
    {
        return $this->get("/orders/{$orderId}");
    }

    /**
     * Submits an API refund request.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function refund(string $orderId, array $body): array
    {
        return $this->post("/orders/{$orderId}/refunds", $body);
    }

    /**
     * Tests the credentials by querying an endpoint or probe.
     *
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        if (! $this->settings->hasCredentials()) {
            throw new GatewayException('Enter the Cashfree App ID and Secret Key.');
        }

        try {
            $this->getOrder('TEST_PROBE_' . time());
        } catch (GatewayException $e) {
            // A 404 "order_not_found" proves authentication succeeded!
            if (str_contains(strtolower($e->getMessage()), 'order') && (str_contains(strtolower($e->getMessage()), 'not found') || str_contains(strtolower($e->getMessage()), '404') || str_contains(strtolower($e->getMessage()), 'does not exist'))) {
                return;
            }

            if (str_contains(strtolower($e->getMessage()), 'invalid client') || str_contains(strtolower($e->getMessage()), 'authentication') || str_contains(strtolower($e->getMessage()), 'unauthorized') || str_contains(strtolower($e->getMessage()), '401')) {
                throw new GatewayException('Cashfree authentication failed: verify your App ID and Secret Key.');
            }

            // Any server response other than bad auth means credentials passed
            return;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function post(string $endpoint, array $body): array
    {
        $headers = $this->headers();

        try {
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->withoutRedirecting()
                ->post($this->baseUrl() . $endpoint, $body);
        } catch (ConnectionException) {
            throw new GatewayException('Could not connect to Cashfree Payments. Please try again.', retryable: true);
        } catch (Throwable $e) {
            throw new GatewayException('Cashfree API request failed: ' . mb_substr($e->getMessage(), 0, 150));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new GatewayException("Cashfree returned an unexpected response (HTTP {$response->status()}).", $response->serverError());
        }

        if ($response->failed()) {
            $msg = $json['message'] ?? $json['error'] ?? "Request failed with HTTP {$response->status()}";
            throw new GatewayException("Cashfree: {$msg}", $response->serverError());
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function get(string $endpoint): array
    {
        $headers = $this->headers();

        try {
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->withoutRedirecting()
                ->get($this->baseUrl() . $endpoint);
        } catch (ConnectionException) {
            throw new GatewayException('Could not connect to Cashfree Payments. Please try again.', retryable: true);
        } catch (Throwable $e) {
            throw new GatewayException('Cashfree API request failed: ' . mb_substr($e->getMessage(), 0, 150));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new GatewayException("Cashfree returned an unexpected response (HTTP {$response->status()}).", $response->serverError());
        }

        if ($response->failed()) {
            $msg = $json['message'] ?? $json['error'] ?? "Request failed with HTTP {$response->status()}";
            throw new GatewayException("Cashfree: {$msg}", $response->serverError());
        }

        return $json;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $appId = $this->settings->appId() ?? throw new GatewayException('Cashfree App ID is missing.');
        $secretKey = $this->settings->secretKey() ?? throw new GatewayException('Cashfree Secret Key is missing.');

        return [
            'x-client-id' => $appId,
            'x-client-secret' => $secretKey,
            'x-api-version' => self::API_VERSION,
        ];
    }
}
