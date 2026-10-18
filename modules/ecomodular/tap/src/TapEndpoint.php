<?php

namespace EcoModular\Tap;

/**
 * Hosts this store is allowed to call, and the shape of a payment page Tap
 * may return. KNET, Benefit and the other local methods use their own hosts,
 * so the payment page is any https address without embedded credentials.
 *
 * @see https://developers.tap.company/reference/charges
 */
final class TapEndpoint
{
    public static function apiBase(): string
    {
        return 'https://api.tap.company';
    }

    public static function isPaymentUrl(string $url): bool
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
            && $host !== ''
            && str_contains($host, '.')
            && filter_var($host, FILTER_VALIDATE_IP) === false;
    }
}
