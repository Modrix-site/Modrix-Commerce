<?php

namespace EcoModular\Telr;

use App\Domains\Payments\Gateway\GatewayException;
use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

/**
 * Telr hosted payment page (order.json) and remote refund (remote.xml).
 *
 * Requests go only to secure.telr.com. Redirects are not followed, and
 * secrets are stripped from any message that might reach the admin UI.
 */
final class TelrApi
{
    public const ORDER_URL = 'https://secure.telr.com/gateway/order.json';

    public const REMOTE_URL = 'https://secure.telr.com/gateway/remote.xml';

    public function __construct(private readonly TelrSettings $settings) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function order(array $body): array
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->withoutRedirecting()
                ->post(self::ORDER_URL, $body);
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach Telr. Please try again.', retryable: true);
        }

        if ($response->failed() && $response->json() === null) {
            throw new GatewayException($this->redact("Telr could not create the payment page (HTTP {$response->status()})."), $response->status() === 429 || $response->serverError());
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new GatewayException('Telr returned an unreadable response.', $response->serverError());
        }

        return $json;
    }

    /**
     * @param  array<string, string>  $transaction
     * @return array{status: string, code: string, message: string, tranref: string}
     *
     * @throws GatewayException
     */
    public function remote(array $transaction): array
    {
        $key = $this->settings->remoteAuthKey() ?? throw new GatewayException('Telr is not configured: add the authentication key.');
        $store = $this->settings->storeId() ?? throw new GatewayException('Telr is not configured: add the store ID.');

        try {
            $response = Http::withBody($this->remoteXml($store, $key, $transaction), 'application/xml')
                ->connectTimeout(5)
                ->timeout(25)
                ->withoutRedirecting()
                ->post(self::REMOTE_URL);
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach Telr to refund this payment. Check the Telr dashboard before trying again.', retryable: false);
        }

        if ($response->failed() && ! str_contains($response->body(), '<')) {
            throw new GatewayException($this->redact("Telr refund request failed (HTTP {$response->status()})."), $response->serverError());
        }

        return $this->remoteResult($response->body());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function errorMessage(array $body): string
    {
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];
        $message = is_string($error['message'] ?? null) ? $error['message'] : 'the request was rejected.';
        $note = is_string($error['note'] ?? null) && $error['note'] !== '' ? " ({$error['note']})" : '';

        return $this->redact('Telr: '.mb_substr($message, 0, 240).$note);
    }

    public function redact(string $message): string
    {
        $message = mb_substr($message, 0, 300);

        foreach (array_filter([$this->settings->authKey(), $this->settings->remoteAuthKey(), $this->settings->webhookSecret()]) as $secret) {
            $message = str_replace($secret, '[redacted]', $message);
        }

        return $message;
    }

    public static function credentialError(string $message): bool
    {
        return (bool) preg_match('/invalid store|authentication|auth(?:entication)? key|not permitted|access denied|\bE04\b|unauthori[sz]ed request/i', $message);
    }

    /**
     * @param  array<string, string>  $transaction
     */
    private function remoteXml(int $store, string $key, array $transaction): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $remote = $doc->createElement('remote');
        $doc->appendChild($remote);
        $this->text($doc, $remote, 'store', (string) $store);
        $this->text($doc, $remote, 'key', $key);
        $tran = $doc->createElement('tran');
        $remote->appendChild($tran);

        foreach ($transaction as $name => $value) {
            $this->text($doc, $tran, $name, $value);
        }

        return (string) $doc->saveXML();
    }

    private function text(DOMDocument $doc, DOMElement $parent, string $name, string $value): void
    {
        $element = $doc->createElement($name);
        $element->appendChild($doc->createTextNode($value));
        $parent->appendChild($element);
    }

    /**
     * @return array{status: string, code: string, message: string, tranref: string}
     *
     * @throws GatewayException
     */
    private function remoteResult(string $body): array
    {
        if (stripos($body, '<!DOCTYPE') !== false || stripos($body, '<!ENTITY') !== false) {
            throw new GatewayException('Telr returned an unreadable refund response.');
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml instanceof SimpleXMLElement) {
            throw new GatewayException('Telr returned an unreadable refund response.');
        }

        $auth = $xml->auth ?? null;
        $status = strtoupper(trim((string) ($auth->status ?? '')));
        $tranref = trim((string) ($auth->tranref ?? ''));
        $message = trim((string) ($auth->message ?? ''));
        $code = trim((string) ($auth->code ?? ''));

        if ($status === '' || $tranref === '') {
            $fallback = trim((string) ($xml->error->message ?? $message));

            throw new GatewayException($this->redact($fallback !== '' ? "Telr: {$fallback}" : 'Telr did not confirm the refund.'));
        }

        return ['status' => $status, 'code' => $code, 'message' => $message, 'tranref' => $tranref];
    }
}
