<?php

namespace ModrixCommerce\CCAvenue;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class CCAvenueApi
{
    public function __construct(private readonly CCAvenueSettings $settings) {}

    public function command(string $command, array $data): array
    {
        $key = $this->settings->secret('working_key');
        $access = $this->settings->secret('access_code');
        if ($key === null || $access === null) {
            throw new GatewayException('Save the CCAvenue access code and working key first.');
        }

        try {
            // Never automatically retry refund requests after an ambiguous network failure.
            $response = Http::asForm()->connectTimeout(10)->timeout(30)->post($this->settings->apiUrl(), [
                'access_code' => $access,
                'command' => $command,
                'request_type' => 'JSON',
                'response_type' => 'JSON',
                'version' => '1.2',
                'enc_request' => CCAvenueCrypto::encrypt(json_encode($data, JSON_THROW_ON_ERROR), $key),
            ]);
            if (! $response->successful()) {
                throw new GatewayException('CCAvenue API is unavailable. Check the merchant dashboard before retrying a refund.', retryable: true);
            }
            parse_str($response->body(), $fields);
            if (($fields['status'] ?? null) !== '0' || ! is_string($fields['enc_response'] ?? null)) {
                throw new GatewayException('CCAvenue rejected the API request. Check API credentials, permissions and IP allowlisting.');
            }
            $result = json_decode(CCAvenueCrypto::decrypt($fields['enc_response'], $key), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($result)) {
                throw new GatewayException('CCAvenue returned an unreadable API response.');
            }

            return $result;
        } catch (GatewayException $e) {
            throw $e;
        } catch (Throwable) {
            // HTTP exception messages can contain credentials or full response bodies.
            throw new GatewayException('CCAvenue request failed. Check the merchant dashboard before retrying a refund.', retryable: true);
        }
    }
}
