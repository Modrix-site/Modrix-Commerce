<?php

namespace EcoModular\CheckoutCom;

/**
 * The merchant's Checkout.com API host. Account-specific prefixes are allowed;
 * anything else is refused so a saved setting cannot redirect requests.
 */
final class CheckoutComEndpoint
{
    public static function isApiBase(string $url): bool
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
            && ($path === '' || $path === '/')
            && ($parts['port'] ?? null) === null
            && self::isApiHost($host);
    }

    public static function isCheckoutHost(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ($host === 'checkout.com' || str_ends_with($host, '.checkout.com'));
    }

    public static function isSandboxHost(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));

        return str_contains($host, '.sandbox.') || str_starts_with($host, 'sandbox.');
    }

    private static function isApiHost(string $host): bool
    {
        return in_array($host, ['api.checkout.com', 'api.sandbox.checkout.com'], true)
            || (bool) preg_match('/^[a-z0-9-]+\.api\.checkout\.com$/', $host)
            || (bool) preg_match('/^[a-z0-9-]+\.api\.sandbox\.checkout\.com$/', $host);
    }
}
