<?php

namespace EcoModular\PhonePe;

/**
 * Generates and validates X-VERIFY checksums for PhonePe Payment Gateway.
 *
 * Mechanics per PhonePe Standard Checkout specification:
 * - POST API (Pay/Refund): SHA256(base64Payload + endpoint + saltKey) + "###" + saltIndex
 * - GET API (Status Check): SHA256(endpoint + saltKey) + "###" + saltIndex
 * - Inbound Webhook Callback: SHA256(rawPayload + saltKey) + "###" + saltIndex
 */
final class PhonePeSignature
{
    /**
     * Signs a POST request with base64 encoded payload.
     */
    public function signPost(string $base64Payload, string $endpoint, string $saltKey, string|int $saltIndex): string
    {
        $stringToHash = $base64Payload . $endpoint . $saltKey;
        $hash = hash('sha256', $stringToHash);

        return $hash . '###' . $saltIndex;
    }

    /**
     * Signs a GET request (such as transaction status check).
     */
    public function signGet(string $endpoint, string $saltKey, string|int $saltIndex): string
    {
        $stringToHash = $endpoint . $saltKey;
        $hash = hash('sha256', $stringToHash);

        return $hash . '###' . $saltIndex;
    }

    /**
     * Verifies an inbound webhook payload against the X-VERIFY header.
     */
    public function verifyWebhook(string $payload, string $receivedSignature, string $saltKey, string|int $expectedSaltIndex): bool
    {
        if ($receivedSignature === '' || $saltKey === '') {
            return false;
        }

        $parts = explode('###', $receivedSignature);
        if (count($parts) !== 2) {
            return false;
        }

        [$receivedHash, $receivedIndex] = $parts;

        if ((string) $receivedIndex !== (string) $expectedSaltIndex) {
            return false;
        }

        $expectedHash = hash('sha256', $payload . $saltKey);

        return hash_equals($expectedHash, $receivedHash);
    }
}
