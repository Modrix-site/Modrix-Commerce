<?php

namespace ModrixCommerce\PayTabs;

final class PayTabsSignature
{
    public static function matches(string $payload, mixed $signature, string $key): bool
    {
        return is_string($signature) && preg_match('/^[a-f0-9]{64}$/Di', $signature)
            && hash_equals(hash_hmac('sha256', $payload, $key), strtolower($signature));
    }

    public static function returnMatches(array $fields, string $key): bool
    {
        $signature = $fields['signature'] ?? null;
        unset($fields['signature']);
        foreach ($fields as $value) {
            if (! is_scalar($value) && $value !== null) {
                return false;
            }
        }
        $fields = array_filter($fields);
        ksort($fields);

        return self::matches(http_build_query($fields, '', '&', PHP_QUERY_RFC1738), $signature, $key);
    }
}
