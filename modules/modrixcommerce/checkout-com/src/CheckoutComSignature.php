<?php

namespace EcoModular\CheckoutCom;

/**
 * Webhook signature from Checkout.com: HMAC-SHA256 of the raw body, hex-encoded
 * in the Cko-Signature header.
 *
 * @see https://www.checkout.com/docs/developer-resources/event-notifications/receive-webhooks
 */
final class CheckoutComSignature
{
    public static function matches(string $payload, string $secret, string $header): bool
    {
        $given = strtolower(trim(explode(',', $header)[0]));

        if ($secret === '' || ! preg_match('/^[a-f0-9]{64}$/', $given)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $given);
    }
}
