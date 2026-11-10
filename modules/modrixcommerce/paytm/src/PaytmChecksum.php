<?php

namespace EcoModular\Paytm;

use RuntimeException;

/**
 * Official Paytm Checksum generation and verification utility.
 *
 * Paytm uses SHA256 hashing and AES-128-CBC encryption to guarantee request
 * and callback integrity between the merchant server and Paytm.
 */
final class PaytmChecksum
{
    private static string $iv = '@@@@&&&&####$$$$';

    public static function encrypt(string $input, string $key): string
    {
        $key = html_entity_decode($key);

        if (! function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL extension is required for Paytm Checksum.');
        }

        $encrypted = openssl_encrypt($input, 'AES-128-CBC', $key, 0, self::$iv);

        if ($encrypted === false) {
            throw new RuntimeException('Failed to encrypt Paytm payload.');
        }

        return $encrypted;
    }

    public static function decrypt(string $encrypted, string $key): string
    {
        $key = html_entity_decode($key);

        if (! function_exists('openssl_decrypt')) {
            throw new RuntimeException('OpenSSL extension is required for Paytm Checksum.');
        }

        $decrypted = openssl_decrypt($encrypted, 'AES-128-CBC', $key, 0, self::$iv);

        if ($decrypted === false) {
            throw new RuntimeException('Failed to decrypt Paytm checksum.');
        }

        return $decrypted;
    }

    /**
     * @param  array<string, mixed>|string  $params
     */
    public static function generateSignature(array|string $params, string $key): string
    {
        if (is_array($params)) {
            $params = self::getStringByParams($params);
        }

        $salt = self::generateRandomString(4);

        return self::calculateChecksum($params, $key, $salt);
    }

    /**
     * @param  array<string, mixed>|string  $params
     */
    public static function verifySignature(array|string $params, string $key, string $checksum): bool
    {
        if (is_array($params)) {
            if (isset($params['CHECKSUMHASH'])) {
                unset($params['CHECKSUMHASH']);
            }
            $params = self::getStringByParams($params);
        }

        try {
            $paytmHash = self::decrypt($checksum, $key);
            $salt = substr($paytmHash, -4);

            return hash_equals($paytmHash, self::calculateHash($params, $salt));
        } catch (\Throwable) {
            return false;
        }
    }

    private static function calculateChecksum(string $params, string $key, string $salt): string
    {
        $hashString = self::calculateHash($params, $salt);

        return self::encrypt($hashString, $key);
    }

    private static function calculateHash(string $params, string $salt): string
    {
        $finalString = $params . '|' . $salt;
        $hash = hash('sha256', $finalString);

        return $hash . $salt;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function getStringByParams(array $params): string
    {
        ksort($params);

        $mapped = array_map(function ($value) {
            if ($value === null || (is_string($value) && strtolower($value) === 'null')) {
                return '';
            }

            return is_scalar($value) ? (string) $value : '';
        }, $params);

        return implode('|', $mapped);
    }

    private static function generateRandomString(int $length): string
    {
        $chars = '9876543210ZYXWVUTSRQPONMLKJIHGFEDCBAabcdefghijklmnopqrstuvwxyz!@#$_';
        $charsLen = strlen($chars);
        $random = '';

        for ($i = 0; $i < $length; $i++) {
            $random .= $chars[random_int(0, $charsLen - 1)];
        }

        return $random;
    }
}
