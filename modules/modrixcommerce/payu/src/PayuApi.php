<?php

namespace EcoModular\PayU;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Communicates with PayU India Web Services (`postservice.php`).
 */
final class PayuApi
{
    public const TEST_PAYMENT_URL = 'https://test.payu.in/_payment';

    public const PRODUCTION_PAYMENT_URL = 'https://secure.payu.in/_payment';

    public const TEST_SERVICE_URL = 'https://test.payu.in/merchant/postservice.php?form=2';

    public const PRODUCTION_SERVICE_URL = 'https://info.payu.in/merchant/postservice.php?form=2';

    public const TIMEOUT_SECONDS = 25;

    public function __construct(
        private readonly PayuSettings $settings,
        private readonly PayuSignature $signature,
    ) {}

    public function paymentUrl(): string
    {
        return $this->settings->testMode() ? self::TEST_PAYMENT_URL : self::PRODUCTION_PAYMENT_URL;
    }

    public function serviceUrl(): string
    {
        return $this->settings->testMode() ? self::TEST_SERVICE_URL : self::PRODUCTION_SERVICE_URL;
    }

    /**
     * Verifies transaction status using the PayU `verify_payment` command.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function verifyPayment(string $txnid): array
    {
        $key = $this->settings->merchantKey();
        $salt = $this->settings->salt();

        if ($key === null || $salt === null) {
            throw new GatewayException('PayU Merchant Key or Salt is missing.');
        }

        $command = 'verify_payment';
        $hash = $this->signature->generateCommandHash($key, $command, $txnid, $salt);

        $params = [
            'key' => $key,
            'command' => $command,
            'var1' => $txnid,
            'hash' => $hash,
        ];

        return $this->postCommand($params);
    }

    /**
     * Submits an API refund/cancellation request using the `cancel_refund_transaction` command.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function refund(string $mihpayid, string $token, string $amount): array
    {
        $key = $this->settings->merchantKey();
        $salt = $this->settings->salt();

        if ($key === null || $salt === null) {
            throw new GatewayException('PayU Merchant Key or Salt is missing.');
        }

        $command = 'cancel_refund_transaction';
        $hash = $this->signature->generateCommandHash($key, $command, $mihpayid, $salt);

        $params = [
            'key' => $key,
            'command' => $command,
            'var1' => $mihpayid,
            'var2' => $token,
            'var3' => $amount,
            'hash' => $hash,
        ];

        return $this->postCommand($params);
    }

    /**
     * Tests connectivity and credential validity against PayU postservice.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function testConnection(): array
    {
        try {
            $key = $this->settings->merchantKey();
            if ($key === null || ! $this->settings->hasSalt()) {
                throw new GatewayException('PayU Merchant Key and Salt must be configured.');
            }

            $response = $this->verifyPayment('PROBE_TEST_' . time());

            if (isset($response['status'])) {
                return [
                    'success' => true,
                    'message' => 'Connected successfully to PayU India (' . ($this->settings->testMode() ? 'Test Environment' : 'Production') . ').',
                ];
            }

            return [
                'success' => false,
                'error' => 'PayU did not return a recognizable response status.',
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
    private function postCommand(array $params): array
    {
        $url = $this->serviceUrl();

        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($url, $params);
        } catch (ConnectionException $e) {
            Log::error('PayU connection error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Network error connecting to PayU service.', 0, $e);
        } catch (Throwable $e) {
            Log::error('PayU post service error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new GatewayException('Failed to communicate with PayU service: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            Log::warning('PayU postservice returned error', ['status' => $status, 'body' => $response->body()]);
            throw new GatewayException("PayU postservice error (HTTP {$status}).");
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            // Some PayU error strings come as raw text or serialized PHP
            $data = json_decode($response->body(), true);
            if (is_array($data)) {
                return $data;
            }
            throw new GatewayException('PayU response could not be parsed as JSON: ' . substr($response->body(), 0, 100));
        }

        return $decoded;
    }
}
