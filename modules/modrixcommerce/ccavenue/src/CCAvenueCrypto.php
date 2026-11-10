<?php

namespace ModrixCommerce\CCAvenue;

use App\Domains\Payments\Gateway\GatewayException;

/** CCAvenue integration-kit AES-128-CBC wire format; not application encryption. */
final class CCAvenueCrypto
{
    private const IV = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f";

    public static function encrypt(string $plain, string $key): string
    {
        $encrypted = openssl_encrypt($plain, 'AES-128-CBC', md5($key, true), OPENSSL_RAW_DATA, self::IV);
        if ($encrypted === false) {
            throw new GatewayException('CCAvenue request encryption failed.');
        }

        return bin2hex($encrypted);
    }

    public static function decrypt(string $cipher, string $key): string
    {
        if ($cipher === '' || strlen($cipher) > 262144 || strlen($cipher) % 32 !== 0 || ! ctype_xdigit($cipher)) {
            throw new GatewayException('CCAvenue returned invalid encrypted data.');
        }
        $plain = openssl_decrypt(hex2bin($cipher), 'AES-128-CBC', md5($key, true), OPENSSL_RAW_DATA, self::IV);
        if ($plain === false) {
            throw new GatewayException('CCAvenue response could not be decrypted.');
        }

        return $plain;
    }
}
