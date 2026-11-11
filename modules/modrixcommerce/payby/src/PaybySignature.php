<?php

namespace EcoModular\PayBy;

use RuntimeException;

/**
 * Handles RSA-SHA256 (SHA256withRSA) asymmetric digital signatures for PayBy.
 */
final class PaybySignature
{
    /**
     * Signs payload string using the merchant's RSA private key.
     */
    public function sign(string $payload, string $privateKey): string
    {
        $pem = $this->formatPrivateKey($privateKey);

        $keyResource = openssl_pkey_get_private($pem);
        if ($keyResource === false) {
            throw new RuntimeException('Invalid PayBy RSA private key.');
        }

        $binarySignature = '';
        $success = openssl_sign($payload, $binarySignature, $keyResource, OPENSSL_ALGO_SHA256);

        if (! $success) {
            throw new RuntimeException('Failed to generate PayBy RSA signature.');
        }

        return base64_encode($binarySignature);
    }

    /**
     * Verifies an incoming payload and base64 signature against an RSA public key.
     */
    public function verify(string $payload, string $base64Signature, string $publicKey): bool
    {
        $pem = $this->formatPublicKey($publicKey);

        $keyResource = openssl_pkey_get_public($pem);
        if ($keyResource === false) {
            return false;
        }

        $binarySignature = base64_decode($base64Signature, true);
        if ($binarySignature === false) {
            return false;
        }

        $result = openssl_verify($payload, $binarySignature, $keyResource, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    private function formatPrivateKey(string $key): string
    {
        $trimmed = trim($key);
        if (str_contains($trimmed, '-----BEGIN')) {
            return $trimmed;
        }

        return "-----BEGIN RSA PRIVATE KEY-----\n".wordwrap($trimmed, 64, "\n", true)."\n-----END RSA PRIVATE KEY-----";
    }

    private function formatPublicKey(string $key): string
    {
        $trimmed = trim($key);
        if (str_contains($trimmed, '-----BEGIN')) {
            return $trimmed;
        }

        return "-----BEGIN PUBLIC KEY-----\n".wordwrap($trimmed, 64, "\n", true)."\n-----END PUBLIC KEY-----";
    }
}
