<?php

namespace EcoModular\PhonePe;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Communicates with PhonePe Standard Checkout APIs (pg/v1).
 */
final class PhonePeApi
{
    public const SANDBOX_URL = 'https://api-preprod.phonepe.com/apis/pg-sandbox';

    public const PRODUCTION_URL = 'https://api.phonepe.com/apis/hermes';

    public function __construct(
        private readonly PhonePeSettings $settings,
        private readonly PhonePeSignature $signature,
    ) {}

    public function baseUrl(): string
    {
        return $this->settings->testMode() ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    /**
     * Initiates a payment session on PhonePe.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function pay(array $payload): array
    {
        $endpoint = '/pg/v1/pay';
        $json = json_encode($payload);
        $base64 = base64_encode($json);

        $saltKey = $this->settings->saltKey() ?? throw new GatewayException('PhonePe Salt Key is missing.');
        $saltIndex = $this->settings->saltIndex();

        $xVerify = $this->signature->signPost($base64, $endpoint, $saltKey, $saltIndex);

        return $this->post($endpoint, ['request' => $base64], [
            'X-VERIFY' => $xVerify,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Checks the transaction status directly with PhonePe.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function checkStatus(string $transactionId): array
    {
        $merchantId = $this->settings->merchantId() ?? throw new GatewayException('PhonePe Merchant ID is missing.');
        $saltKey = $this->settings->saltKey() ?? throw new GatewayException('PhonePe Salt Key is missing.');
        $saltIndex = $this->settings->saltIndex();

        $endpoint = "/pg/v1/status/{$merchantId}/{$transactionId}";
        $xVerify = $this->signature->signGet($endpoint, $saltKey, $saltIndex);

        return $this->get($endpoint, [
            'X-VERIFY' => $xVerify,
            'X-MERCHANT-ID' => $merchantId,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Submits an API refund for a PhonePe transaction.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function refund(array $payload): array
    {
        $endpoint = '/pg/v1/refund';
        $json = json_encode($payload);
        $base64 = base64_encode($json);

        $saltKey = $this->settings->saltKey() ?? throw new GatewayException('PhonePe Salt Key is missing.');
        $saltIndex = $this->settings->saltIndex();

        $xVerify = $this->signature->signPost($base64, $endpoint, $saltKey, $saltIndex);

        return $this->post($endpoint, ['request' => $base64], [
            'X-VERIFY' => $xVerify,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Tests credentials via a status check probe.
     *
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        if (! $this->settings->hasCredentials()) {
            throw new GatewayException('Enter the PhonePe Merchant ID and Salt Key.');
        }

        try {
            $this->checkStatus('TEST_PROBE_' . time());
        } catch (GatewayException $e) {
            $msg = strtolower($e->getMessage());
            // A transaction not found response confirms the signature and Merchant ID passed authentication!
            if (str_contains($msg, 'not found') || str_contains($msg, 'transaction') || str_contains($msg, 'order')) {
                return;
            }

            if (str_contains($msg, 'unauthorized') || str_contains($msg, 'invalid') || str_contains($msg, 'checksum') || str_contains($msg, 'signature')) {
                throw new GatewayException('PhonePe authentication failed: verify your Merchant ID, Salt Key, and Salt Index.');
            }

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function post(string $endpoint, array $body, array $headers): array
    {
        try {
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->withoutRedirecting()
                ->post($this->baseUrl() . $endpoint, $body);
        } catch (ConnectionException) {
            throw new GatewayException('Could not connect to PhonePe. Please try again.', retryable: true);
        } catch (Throwable $e) {
            throw new GatewayException('PhonePe API request failed: ' . mb_substr($e->getMessage(), 0, 150));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new GatewayException("PhonePe returned an unexpected response (HTTP {$response->status()}).", $response->serverError());
        }

        if ($response->failed() && ! isset($json['code'])) {
            $msg = $json['message'] ?? "HTTP {$response->status()}";
            throw new GatewayException("PhonePe: {$msg}", $response->serverError());
        }

        return $json;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function get(string $endpoint, array $headers): array
    {
        try {
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->withoutRedirecting()
                ->get($this->baseUrl() . $endpoint);
        } catch (ConnectionException) {
            throw new GatewayException('Could not connect to PhonePe. Please try again.', retryable: true);
        } catch (Throwable $e) {
            throw new GatewayException('PhonePe API request failed: ' . mb_substr($e->getMessage(), 0, 150));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new GatewayException("PhonePe returned an unexpected response (HTTP {$response->status()}).", $response->serverError());
        }

        if ($response->failed() && ! isset($json['code'])) {
            $msg = $json['message'] ?? "HTTP {$response->status()}";
            throw new GatewayException("PhonePe: {$msg}", $response->serverError());
        }

        return $json;
    }
}
