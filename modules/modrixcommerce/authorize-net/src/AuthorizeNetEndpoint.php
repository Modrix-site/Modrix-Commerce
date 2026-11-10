<?php

namespace EcoModular\AuthorizeNet;

/**
 * Official Authorize.Net hosts. Payment form posts and API calls are refused
 * for any other host.
 *
 * @see https://developer.authorize.net/api/reference/features/accept-hosted.html
 */
final class AuthorizeNetEndpoint
{
    public static function apiUrl(bool $sandbox): string
    {
        return $sandbox
            ? 'https://apitest.authorize.net/xml/v1/request.api'
            : 'https://api.authorize.net/xml/v1/request.api';
    }

    public static function formUrl(bool $sandbox): string
    {
        return $sandbox
            ? 'https://test.authorize.net/payment/payment'
            : 'https://accept.authorize.net/payment/payment';
    }

    public static function isFormUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = $parts['path'] ?? '';

        return in_array($host, ['test.authorize.net', 'accept.authorize.net'], true)
            && $path === '/payment/payment';
    }

    public static function isToken(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9+\/=]{20,2000}$/', $token) === 1;
    }
}
