<?php

namespace EcoModular\Paypal;

/**
 * PayPal hosts this store is allowed to call or redirect a customer to.
 *
 * @see https://developer.paypal.com/api/rest/
 */
final class PaypalEndpoint
{
    public static function apiBase(bool $sandbox): string
    {
        return $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    public static function isApprovalUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ($parts['port'] ?? null) === null
            && in_array($host, ['www.paypal.com', 'www.sandbox.paypal.com'], true);
    }

    /**
     * The certificate URL PayPal puts on a webhook. Anything else is refused
     * before it is sent back to PayPal's verification API.
     */
    public static function isCertificateUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment'])
            && ($parts['port'] ?? null) === null
            && in_array($host, ['api-m.paypal.com', 'api-m.sandbox.paypal.com', 'api.paypal.com', 'api.sandbox.paypal.com'], true)
            && str_starts_with($path, '/v1/notifications/certs/');
    }
}
