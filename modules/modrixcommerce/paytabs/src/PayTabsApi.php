<?php

namespace ModrixCommerce\PayTabs;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class PayTabsApi
{
    public function __construct(private readonly PayTabsSettings $settings) {}

    public function snapshot(): object
    {
        return (object) [
            'profile_id' => $this->settings->profileId(), 'region' => $this->settings->value('region'),
            'test_mode' => $this->settings->value('test_mode'), 'key_hash' => hash('sha256', $this->settings->serverKey() ?? ''),
        ];
    }

    public function post(string $path, array $body, ?object $context = null): array
    {
        $context ??= $this->snapshot();
        $key = $this->settings->serverKey((bool) $context->test_mode);
        $host = PayTabsSettings::REGIONS[$context->region][1] ?? null;
        if ($key === null || $host === null || $context->profile_id === ''
            || $this->settings->profileId((bool) $context->test_mode) !== (string) $context->profile_id
            || ! hash_equals($context->key_hash, hash('sha256', $key))) {
            throw new GatewayException('Restore this payment’s original PayTabs profile and server key before processing it.');
        }
        if (! in_array($path, ['request', 'query'], true)) {
            throw new GatewayException('Unsupported PayTabs API operation.');
        }
        try {
            // No transport retries: an ambiguous financial request must be reconciled first.
            $response = Http::acceptJson()->asJson()->withHeaders(['Authorization' => $key])
                ->withoutRedirecting()->connectTimeout(5)->timeout(25)
                ->post($host.'/payment/'.$path, ['profile_id' => (int) $context->profile_id] + $body);
            $data = $response->json();
            if (! $response->successful() || ! is_array($data) || array_is_list($data) || isset($data['code'])) {
                throw new GatewayException('PayTabs rejected the request. Check the profile, server key, region and API permissions. Check the dashboard before retrying a refund.');
            }

            return $data;
        } catch (GatewayException $e) {
            throw $e;
        } catch (Throwable) {
            // Do not expose HTTP exception bodies: they can contain secrets or customer data.
            throw new GatewayException('PayTabs could not be reached. Check the dashboard before retrying a refund.', retryable: true);
        }
    }
}
