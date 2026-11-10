<?php

namespace EcoModular\Cashfree;

/**
 * Computes and verifies webhook signatures for Cashfree Payments.
 *
 * Algorithm per Cashfree API specification:
 * 1. Concatenate the timestamp header and the exact raw request payload: $timestamp . $payload
 * 2. Calculate the HMAC-SHA256 hash using the merchant's Secret Key (binary).
 * 3. Base64-encode the resulting hash.
 * 4. Verify against the 'x-webhook-signature' header using timing-safe hash_equals().
 */
final class CashfreeSignature
{
    public function calculate(string $payload, string $timestamp, string $secretKey): string
    {
        $signedPayload = $timestamp . $payload;

        return base64_encode(hash_hmac('sha256', $signedPayload, $secretKey, true));
    }

    public function verify(string $payload, string $signature, string $timestamp, string $secretKey): bool
    {
        if ($signature === '' || $timestamp === '' || $secretKey === '') {
            return false;
        }

        $expected = $this->calculate($payload, $timestamp, $secretKey);

        return hash_equals($expected, $signature);
    }
}
