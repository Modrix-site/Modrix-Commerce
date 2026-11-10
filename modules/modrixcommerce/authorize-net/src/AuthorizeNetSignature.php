<?php

namespace EcoModular\AuthorizeNet;

/**
 * Webhook signatures are HMAC-SHA512 of the raw body. The merchant Signature
 * Key is hex, and Authorize.Net uses the decoded bytes as the HMAC key.
 *
 * @see https://developer.authorize.net/api/reference/features/webhooks.html
 */
final class AuthorizeNetSignature
{
    public static function matches(string $payload, string $header, string $hexKey): bool
    {
        if (preg_match('/^[A-Fa-f0-9]{64,256}$/', $hexKey) !== 1 || strlen($hexKey) % 2 !== 0) {
            return false;
        }

        if (preg_match('/^sha512=([A-Fa-f0-9]+)$/i', trim($header), $matches) !== 1) {
            return false;
        }

        $key = hex2bin($hexKey);

        if ($key === false) {
            return false;
        }

        $computed = hash_hmac('sha512', $payload, $key);

        return hash_equals(strtolower($computed), strtolower($matches[1]));
    }
}
