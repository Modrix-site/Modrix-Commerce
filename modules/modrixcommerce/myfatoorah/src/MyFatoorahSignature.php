<?php

namespace EcoModular\MyFatoorah;

/**
 * Handles webhook signature verification for MyFatoorah.
 */
final class MyFatoorahSignature
{
    /**
     * Generates expected HMAC-SHA256 signature for a webhook payload.
     */
    public function generateSignature(string $payload, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $payload, $secret, true));
    }

    /**
     * Verifies the signature from the MyFatoorah-Signature header against the raw webhook body.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        if (trim($signature) === '' || trim($secret) === '') {
            return false;
        }

        $expectedBase64 = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        $expectedHex = hash_hmac('sha256', $payload, $secret);

        $cleanSig = trim($signature);

        return hash_equals($expectedBase64, $cleanSig) || hash_equals($expectedHex, strtolower($cleanSig));
    }
}
