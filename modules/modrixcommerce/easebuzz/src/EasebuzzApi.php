<?php

namespace EcoModular\Easebuzz;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Communicates with Easebuzz payment and transaction APIs.
 */
final class EasebuzzApi
{
    public const TEST_PAY_URL = 'https://testpay.easebuzz.in';

    public const PRODUCTION_PAY_URL = 'https://pay.easebuzz.in';

    public const TEST_DASHBOARD_URL = 'https://testdashboard.easebuzz.in';

    public const PRODUCTION_DASHBOARD_URL = 'https://dashboard.easebuzz.in';

    public const TIMEOUT_SECONDS = 25;

    public function __construct(
        private readonly EasebuzzSettings $settings,
        private readonly EasebuzzSignature $signature,
    ) {}

    public function payBaseUrl(): string
    {
        return $this->settings->testMode() ? self::TEST_PAY_URL : self::PRODUCTION_PAY_URL;
    }

    public function dashboardBaseUrl(): string
    {
        return $this->settings->testMode() ? self::TEST_DASHBOARD_URL : self::PRODUCTION_DASHBOARD_URL;
    }

    public function initiateUrl(): string
    {
        return $this->payBaseUrl() . '/payment/initiateLink';
    }

    public function checkoutUrl(string $accessKey): string
    {
        if (str_starts_with($accessKey, 'http://') || str_starts_with($accessKey, 'https://')) {
            return $accessKey;
        }

        return $this->payBaseUrl() . '/pay/' . $accessKey;
    }

    /**
     * Initiates a payment session with Easebuzz.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function initiatePayment(array $params): array
    {
        $key = $this->settings->merchantKey();
        $salt = $this->settings->salt();

        if ($key === null || $salt === null) {
            throw new GatewayException('Easebuzz Merchant Key or Salt is missing.');
        }

        if (! isset($params['hash'])) {
            $params['hash'] = $this->signature->generateInitiateHash(
                $key,
                (string) ($params['txnid'] ?? ''),
                (string) ($params['amount'] ?? ''),
                (string) ($params['productinfo'] ?? ''),
                (string) ($params['firstname'] ?? ''),
                (string) ($params['email'] ?? ''),
                $salt,
                (string) ($params['udf1'] ?? ''),
                (string) ($params['udf2'] ?? ''),
                (string) ($params['udf3'] ?? ''),
                (string) ($params['udf4'] ?? ''),
                (string) ($params['udf5'] ?? ''),
                (string) ($params['udf6'] ?? ''),
                (string) ($params['udf7'] ?? ''),
                (string) ($params['udf8'] ?? ''),
                (string) ($params['udf9'] ?? ''),
                (string) ($params['udf10'] ?? ''),
            );
        }

        $params['key'] = $key;

        $url = $this->initiateUrl();

        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($url, $params);
        } catch (ConnectionException $e) {
            Log::error('Easebuzz initiate payment connection error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Network error connecting to Easebuzz service.', 0, $e);
        } catch (Throwable $e) {
            Log::error('Easebuzz initiate payment error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Failed to initiate Easebuzz payment: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            Log::warning('Easebuzz initiateLink returned error', ['status' => $status, 'body' => $response->body()]);
            throw new GatewayException("Easebuzz initiateLink error (HTTP {$status}).");
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            $decoded = json_decode($response->body(), true);
            if (! is_array($decoded)) {
                throw new GatewayException('Easebuzz response could not be parsed as JSON: ' . substr($response->body(), 0, 100));
            }
        }

        if (isset($decoded['status']) && (int) $decoded['status'] === 0) {
            $errMsg = is_string($decoded['data'] ?? null) ? $decoded['data'] : 'Easebuzz declined transaction initiation.';
            throw new GatewayException("Easebuzz: {$errMsg}");
        }

        return $decoded;
    }

    /**
     * Retrieves transaction status from Easebuzz.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function retrieveTransaction(string $txnid, string $amount, string $email, string $phone): array
    {
        $key = $this->settings->merchantKey();
        $salt = $this->settings->salt();

        if ($key === null || $salt === null) {
            throw new GatewayException('Easebuzz Merchant Key or Salt is missing.');
        }

        $hash = $this->signature->generateRetrieveHash($key, $txnid, $amount, $email, $phone, $salt);

        $params = [
            'key' => $key,
            'txnid' => $txnid,
            'amount' => $amount,
            'email' => $email,
            'phone' => $phone,
            'hash' => $hash,
        ];

        $url = $this->dashboardBaseUrl() . '/transaction/v1/retrieve';

        return $this->postDashboard($url, $params);
    }

    /**
     * Submits a refund request to Easebuzz.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function refund(string $txnid, string $phone, string $email, string $amount, string $refundAmount): array
    {
        $key = $this->settings->merchantKey();
        $salt = $this->settings->salt();

        if ($key === null || $salt === null) {
            throw new GatewayException('Easebuzz Merchant Key or Salt is missing.');
        }

        $hash = $this->signature->generateRefundHash($key, $txnid, $phone, $email, $amount, $refundAmount, $salt);

        $params = [
            'key' => $key,
            'txnid' => $txnid,
            'phone' => $phone,
            'email' => $email,
            'amount' => $amount,
            'refund_amount' => $refundAmount,
            'hash' => $hash,
        ];

        $url = $this->dashboardBaseUrl() . '/transaction/v1/refund';

        return $this->postDashboard($url, $params);
    }

    /**
     * Tests connectivity and credential validity against Easebuzz API.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function testConnection(): array
    {
        try {
            $key = $this->settings->merchantKey();
            if ($key === null || ! $this->settings->hasSalt()) {
                throw new GatewayException('Easebuzz Merchant Key and Salt must be configured.');
            }

            $response = $this->retrieveTransaction(
                'PROBE_TEST_' . time(),
                '1.00',
                'probe@example.com',
                '9999999999',
            );

            if (isset($response['status'])) {
                return [
                    'success' => true,
                    'message' => 'Connected successfully to Easebuzz India (' . ($this->settings->testMode() ? 'Test Environment' : 'Production') . ').',
                ];
            }

            return [
                'success' => false,
                'error' => 'Easebuzz did not return a recognizable response status.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function postDashboard(string $url, array $params): array
    {
        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($url, $params);
        } catch (ConnectionException $e) {
            Log::error('Easebuzz connection error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Network error connecting to Easebuzz service.', 0, $e);
        } catch (Throwable $e) {
            Log::error('Easebuzz dashboard service error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Failed to communicate with Easebuzz service: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            Log::warning('Easebuzz dashboard returned error', ['status' => $status, 'body' => $response->body()]);
            throw new GatewayException("Easebuzz dashboard service error (HTTP {$status}).");
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            $decoded = json_decode($response->body(), true);
            if (! is_array($decoded)) {
                throw new GatewayException('Easebuzz response could not be parsed as JSON: ' . substr($response->body(), 0, 100));
            }
        }

        return $decoded;
    }
}
