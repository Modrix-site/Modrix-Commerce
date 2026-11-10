<?php

namespace EcoModular\AmazonPaymentServices;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Communicates with Amazon Payment Services (APS) Payment APIs.
 */
final class AmazonPaymentServicesApi
{
    public const CHECKOUT_URL_SANDBOX = 'https://sbcheckout.payfort.com/FortAPI/paymentPage';

    public const CHECKOUT_URL_PRODUCTION = 'https://checkout.payfort.com/FortAPI/paymentPage';

    public const API_URL_SANDBOX = 'https://sbpaymentservices.payfort.com/FortAPI/paymentApi';

    public const API_URL_PRODUCTION = 'https://paymentservices.payfort.com/FortAPI/paymentApi';

    public function __construct(
        private readonly AmazonPaymentServicesSettings $settings,
        private readonly AmazonPaymentServicesSignature $signature,
        private readonly AmazonPaymentServicesAmount $amountHelper,
    ) {}

    public function checkoutUrl(): string
    {
        return $this->settings->testMode()
            ? self::CHECKOUT_URL_SANDBOX
            : self::CHECKOUT_URL_PRODUCTION;
    }

    public function apiUrl(): string
    {
        return $this->settings->testMode()
            ? self::API_URL_SANDBOX
            : self::API_URL_PRODUCTION;
    }

    /**
     * Queries the status of an existing transaction via the CHECK_STATUS command.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function checkStatus(string $merchantReference, ?string $fortId = null): array
    {
        $merchantId = $this->settings->merchantIdentifier()
            ?? throw new GatewayException('APS is not configured: merchant identifier is missing.');
        $accessCode = $this->settings->accessCode()
            ?? throw new GatewayException('APS is not configured: access code is missing.');
        $requestPhrase = $this->settings->shaRequestPhrase()
            ?? throw new GatewayException('APS is not configured: SHA request phrase is missing.');
        $responsePhrase = $this->settings->shaResponsePhrase()
            ?? throw new GatewayException('APS is not configured: SHA response phrase is missing.');

        $params = [
            'query_command' => 'CHECK_STATUS',
            'access_code' => $accessCode,
            'merchant_identifier' => $merchantId,
            'merchant_reference' => $merchantReference,
            'language' => $this->settings->language(),
        ];

        if ($fortId !== null && trim($fortId) !== '') {
            $params['fort_id'] = trim($fortId);
        }

        $params['signature'] = $this->signature->calculate(
            $params,
            $requestPhrase,
            $this->settings->shaType(),
        );

        $response = $this->postApi($params);

        $signature = (string) ($response['signature'] ?? '');
        if ($signature !== '' && ! $this->signature->verify($response, $signature, $responsePhrase, $this->settings->shaType())) {
            throw new GatewayException('APS status check returned an invalid response signature.');
        }

        return $response;
    }

    /**
     * Submits a refund for a captured payment.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function refund(string $merchantReference, string $fortId, Money $amount): array
    {
        $merchantId = $this->settings->merchantIdentifier()
            ?? throw new GatewayException('APS is not configured: merchant identifier is missing.');
        $accessCode = $this->settings->accessCode()
            ?? throw new GatewayException('APS is not configured: access code is missing.');
        $requestPhrase = $this->settings->shaRequestPhrase()
            ?? throw new GatewayException('APS is not configured: SHA request phrase is missing.');
        $responsePhrase = $this->settings->shaResponsePhrase()
            ?? throw new GatewayException('APS is not configured: SHA response phrase is missing.');

        $params = [
            'command' => 'REFUND',
            'access_code' => $accessCode,
            'merchant_identifier' => $merchantId,
            'merchant_reference' => $merchantReference,
            'fort_id' => $fortId,
            'amount' => (string) $this->amountHelper->toMinor($amount),
            'currency' => $amount->currency(),
            'language' => $this->settings->language(),
        ];

        $params['signature'] = $this->signature->calculate(
            $params,
            $requestPhrase,
            $this->settings->shaType(),
        );

        $response = $this->postApi($params);

        $signature = (string) ($response['signature'] ?? '');
        if ($signature !== '' && ! $this->signature->verify($response, $signature, $responsePhrase, $this->settings->shaType())) {
            throw new GatewayException('APS refund response returned an invalid signature.');
        }

        return $response;
    }

    /**
     * Tests the credentials by running a status inquiry check against the API endpoint.
     *
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        if (! $this->settings->hasCredentials()) {
            throw new GatewayException('Enter the Merchant Identifier, Access Code, and both SHA Passphrases.');
        }

        try {
            $response = $this->checkStatus('TEST-PROBE-' . time());
            $responseCode = (string) ($response['response_code'] ?? '');

            // 12000 = success query, or 12044 = transaction not found (both prove authentication succeeded)
            if (in_array(substr($responseCode, 0, 2), ['12', '14', '00'], true) || $responseCode === '12044' || $responseCode === '12042') {
                return;
            }

            if (isset($response['response_message'])) {
                throw new GatewayException('APS: ' . mb_substr((string) $response['response_message'], 0, 200));
            }
        } catch (GatewayException $e) {
            // If the query returns "Transaction not found", credentials and signatures are valid!
            if (str_contains($e->getMessage(), 'not found') || str_contains($e->getMessage(), '12044')) {
                return;
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function postApi(array $body): array
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->withoutRedirecting()
                ->post($this->apiUrl(), $body);
        } catch (ConnectionException) {
            throw new GatewayException('Could not connect to Amazon Payment Services. Please try again.', retryable: true);
        } catch (Throwable $e) {
            throw new GatewayException('Could not reach Amazon Payment Services: ' . mb_substr($e->getMessage(), 0, 150));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new GatewayException('Amazon Payment Services returned an unexpected response format.', $response->serverError());
        }

        return $json;
    }
}
