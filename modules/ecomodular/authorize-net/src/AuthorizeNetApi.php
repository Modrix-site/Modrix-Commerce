<?php

namespace EcoModular\AuthorizeNet;

use App\Domains\Payments\Gateway\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Authorize.Net JSON API. Every call goes to the sandbox or live request
 * endpoint for the saved mode. Responses may start with a UTF-8 BOM.
 *
 * @see https://developer.authorize.net/api/reference/index.html
 */
final class AuthorizeNetApi
{
    public function __construct(private readonly AuthorizeNetSettings $settings) {}

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function call(string $requestName, array $fields, bool $retryable = false): array
    {
        $login = $this->settings->apiLoginId() ?? throw new GatewayException('Authorize.Net is not configured: add the API Login ID.');
        $key = $this->settings->transactionKey() ?? throw new GatewayException('Authorize.Net is not configured: add the transaction key.');

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->withoutRedirecting()
                ->post($this->settings->apiUrl(), [
                    $requestName => ['merchantAuthentication' => ['name' => $login, 'transactionKey' => $key], ...$fields],
                ]);
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach Authorize.Net. Please try again.', retryable: $retryable);
        }

        return $this->decode($response, $retryable, $login, $key);
    }

    /**
     * @throws GatewayException
     */
    public function testConnection(): void
    {
        $this->call('authenticateTestRequest', []);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function decode(Response $response, bool $retryable, string $login, string $key): array
    {
        $status = $response->status();

        if ($status === 408 || $status === 429 || $status >= 500) {
            throw new GatewayException('Authorize.Net is unavailable. Please try again.', retryable: $retryable);
        }

        $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->body()) ?? '';
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new GatewayException('Authorize.Net returned an unreadable response.', retryable: $retryable);
        }

        $result = $decoded['messages']['resultCode'] ?? null;

        if ($result !== 'Ok') {
            $code = (string) ($decoded['messages']['message'][0]['code'] ?? '');
            $text = (string) ($decoded['messages']['message'][0]['text'] ?? 'Authorize.Net rejected the request.');

            if ($code === 'E00007') {
                $text = 'Authorize.Net rejected the API Login ID or transaction key.';
            }

            throw new GatewayException($this->redact($text, $login, $key), retryable: $retryable && in_array($code, ['E00001', 'E00053'], true));
        }

        return $decoded;
    }

    private function redact(string $message, string $login, string $key): string
    {
        return str_replace([$login, $key], ['[login]', '[key]'], $message);
    }
}
