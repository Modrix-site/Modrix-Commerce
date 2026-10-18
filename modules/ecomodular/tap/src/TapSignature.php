<?php

namespace EcoModular\Tap;

use App\Domains\Payments\Gateway\GatewayException;

/**
 * Tap's hashstring. The signed text is the posted id, the amount rounded to
 * the currency's decimals, the currency, both reference fields (empty when
 * Tap omits them), the status and the created timestamp.
 *
 * @see https://developers.tap.company/docs/webhook
 */
final class TapSignature
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function matches(array $payload, string $header, string $secret): bool
    {
        $header = strtolower(trim($header));
        $signed = self::toSign($payload);

        if ($signed === null || preg_match('/^[a-f0-9]{64}$/', $header) !== 1) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $signed, $secret), $header);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function toSign(array $payload): ?string
    {
        $id = $payload['id'] ?? null;
        $currency = $payload['currency'] ?? null;
        $status = $payload['status'] ?? null;
        $created = self::created($payload);

        if (! is_string($id) || $id === '' || ! is_string($currency) || ! is_string($status) || $created === null) {
            return null;
        }

        try {
            $amount = TapAmount::hashValue($payload['amount'] ?? null, $currency);
        } catch (GatewayException) {
            return null;
        }

        $reference = is_array($payload['reference'] ?? null) ? $payload['reference'] : [];
        $gateway = self::reference($reference['gateway'] ?? null);
        $payment = self::reference($reference['payment'] ?? null);

        return 'x_id'.$id
            .'x_amount'.$amount
            .'x_currency'.strtoupper($currency)
            .'x_gateway_reference'.$gateway
            .'x_payment_reference'.$payment
            .'x_status'.$status
            .'x_created'.$created;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function created(array $payload): ?string
    {
        $transaction = is_array($payload['transaction'] ?? null) ? $payload['transaction'] : [];
        $created = $transaction['created'] ?? ($payload['created'] ?? null);

        if (is_int($created)) {
            return (string) $created;
        }

        return is_string($created) && $created !== '' ? $created : null;
    }

    private static function reference(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) ? $value : '';
    }
}
