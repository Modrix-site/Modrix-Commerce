<?php

namespace EcoModular\Telr;

/**
 * Transaction-advice signature from Telr's webhook documentation.
 *
 * The check string is the secret key and the transaction fields below, joined
 * with colons and hashed with SHA-1. When Telr includes tran_order (only after
 * they enable it on the account), it sits between tran_firstref and tran_currency.
 */
final class TelrSignature
{
    /** @var list<string> */
    private const FIELDS = [
        'tran_store',
        'tran_type',
        'tran_class',
        'tran_test',
        'tran_ref',
        'tran_prevref',
        'tran_firstref',
        'tran_currency',
        'tran_amount',
        'tran_cartid',
        'tran_desc',
        'tran_status',
        'tran_authcode',
        'tran_authmessage',
    ];

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function matches(array $fields, string $secret): bool
    {
        $given = $fields['tran_check'] ?? null;

        if (! is_string($given) || $given === '' || $secret === '') {
            return false;
        }

        return hash_equals(self::hash($fields, $secret), strtolower($given));
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function hash(array $fields, string $secret): string
    {
        $parts = [$secret];

        foreach (self::FIELDS as $name) {
            if ($name === 'tran_currency' && array_key_exists('tran_order', $fields)) {
                $parts[] = self::value($fields['tran_order']);
            }

            $parts[] = self::value($fields[$name] ?? '');
        }

        return sha1(implode(':', $parts));
    }

    private static function value(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) ? $value : '';
    }
}
