<?php

namespace EcoModular\GdprCookieConsent\Support;

use Illuminate\Http\Request;

/**
 * Best-effort visitor country detection in order of reliability. Reads the
 * headers most reverse proxies and CDNs add, or an app-supplied value. No
 * IP-based geolocation database ships with the module: set GEOIP_COUNTRY_CODE
 * (or one of the trusted headers) at the edge instead.
 *
 * Returns an uppercase ISO 3166-1 alpha-2 code, or null when unknown.
 */
final class CountryDetector
{
    private const TRUSTED_HEADERS = [
        'HTTP_CF_IPCOUNTRY',      // Cloudflare
        'HTTP_X_COUNTRY_CODE',    // common proxy convention
        'HTTP_GEOIP_COUNTRY_CODE',
        'HTTP_X_GEO_COUNTRY',
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY', // AWS CloudFront geolocation headers
    ];

    public static function detect(Request $request): ?string
    {
        $configured = config('gdpr.country_code');

        if (is_string($configured) && trim($configured) !== '') {
            return self::normalize(trim($configured));
        }

        foreach (self::TRUSTED_HEADERS as $header) {
            $value = $request->server->get($header);
            if (is_string($value) && trim($value) !== '') {
                $code = self::normalize(trim($value));
                if ($code !== null) {
                    return $code;
                }
            }
        }

        return null;
    }

    private static function normalize(string $value): ?string
    {
        if (! preg_match('/^[A-Za-z]{2}$/', $value)) {
            return null;
        }

        return strtoupper($value);
    }
}
