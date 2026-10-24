<?php

namespace Marketplace\Services;

use InvalidArgumentException;
use SodiumException;

/**
 * Ed25519 signatures over canonical JSON (ADR-034).
 *
 * The marketplace server signs release metadata and license tokens with its
 * secret key; merchant stores verify them against the publisher public keys
 * they trust (config `ecomodular.marketplace.trusted_keys`). Uses libsodium,
 * or paragonie/sodium_compat where the extension is not installed.
 */
final class Signing
{
    /**
     * Stable byte representation: keys sorted recursively, no escaping of
     * slashes or unicode, so signer and verifier hash exactly the same bytes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function canonical(array $data): string
    {
        return json_encode(self::sort($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Short identifier of a public key: first 16 hex characters of its SHA-256.
     */
    public static function keyId(string $publicKey): string
    {
        return substr(hash('sha256', $publicKey), 0, 16);
    }

    /**
     * @return array{secret: string, public: string} base64-encoded key pair
     */
    public static function generateKeyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
        ];
    }

    /**
     * Signs data with the configured server key.
     *
     * @param  array<string, mixed>  $data  must contain "key_id" of the signing key
     * @return string base64 signature
     */
    public static function sign(array $data, string $secretKeyBase64): string
    {
        $secret = base64_decode($secretKeyBase64, true);

        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('The marketplace signing key is not a valid Ed25519 secret key.');
        }

        return base64_encode(sodium_crypto_sign_detached(self::canonical($data), $secret));
    }

    public static function publicKeyFromSecret(string $secretKeyBase64): string
    {
        $secret = base64_decode($secretKeyBase64, true);

        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('The marketplace signing key is not a valid Ed25519 secret key.');
        }

        return base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret));
    }

    /**
     * Verifies a signature against the trusted public keys. The key is
     * chosen by the "key_id" inside the signed data; unknown ids fail.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $trustedKeys  base64 public keys
     */
    public static function verify(array $data, string $signatureBase64, array $trustedKeys): bool
    {
        $signature = base64_decode($signatureBase64, true);
        $keyId = $data['key_id'] ?? null;

        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || ! is_string($keyId)) {
            return false;
        }

        foreach ($trustedKeys as $encoded) {
            $key = base64_decode($encoded, true);

            if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || ! hash_equals(self::keyId($key), $keyId)) {
                continue;
            }

            try {
                return sodium_crypto_sign_verify_detached($signature, self::canonical($data), $key);
            } catch (SodiumException) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function sort(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data, SORT_STRING);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sort($value);
            }
        }

        return $data;
    }
}
