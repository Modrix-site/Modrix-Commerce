<?php

namespace ModrixCommerce\Razorpay;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class RazorpayApi
{
    public function __construct(private readonly RazorpaySettings $settings) {}

    public function snapshot(): object
    {
        return (object) ['key_id' => $this->settings->keyId(), 'test_mode' => $this->settings->value('test_mode'), 'secret_hash' => hash('sha256', $this->settings->secret() ?? '')];
    }

    public function credentials(object $context): array
    {
        $secret = $this->settings->secret('key_secret', (bool) $context->test_mode);
        if ($secret === null || $this->settings->keyId((bool) $context->test_mode) !== $context->key_id
            || ! hash_equals($context->secret_hash, hash('sha256', $secret))) {
            throw new GatewayException('Restore this payment’s original Razorpay key ID and secret to process it.');
        }

        return [$context->key_id, $secret];
    }

    public function request(string $method, string $path, array $body = [], ?object $context = null, array $headers = []): array
    {
        if (! in_array($method, ['get', 'post'], true) || ! preg_match('#^(orders(?:/order_[A-Za-z0-9]+(?:/payments)?)?|payments/pay_[A-Za-z0-9]+(?:/capture|/refund)?|refunds/rfnd_[A-Za-z0-9]+)$#D', $path)) {
            throw new GatewayException('Unsupported Razorpay API operation.');
        }
        [$id, $secret] = $this->credentials($context ?? $this->snapshot());
        try {
            $http = Http::acceptJson()->asJson()->withBasicAuth($id, $secret)->withHeaders($headers)
                ->withoutRedirecting()->connectTimeout(5)->timeout(25);
            $response = $http->{$method}('https://api.razorpay.com/v1/'.$path, $body);
            $data = $response->json();
            if (! $response->successful() || ! is_array($data) || array_is_list($data) || isset($data['error'])) {
                throw new GatewayException('Razorpay rejected the request. Check your credentials, account permissions and merchant dashboard before retrying.');
            }

            return $data;
        } catch (GatewayException $e) {
            throw $e;
        } catch (Throwable) {
            throw new GatewayException('Razorpay could not be reached. Reconcile the transaction before retrying a financial request.', retryable: true);
        }
    }
}
