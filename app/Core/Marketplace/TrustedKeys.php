<?php

namespace App\Core\Marketplace;

/**
 * Ed25519 public keys trusted to sign what Modrix Cloud serves (packages,
 * licenses and core releases). Configuration only, never an admin setting.
 */
final class TrustedKeys
{
    /** The official Modrix Cloud's Ed25519 public key, which signs every release it serves. */
    public const OFFICIAL = 'Z8CzGqObdJVPKGDbgzoAVdDY8nL68NTMYM2r80sPMyk=';

    /**
     * The official key plus any configured (ECOMODULAR_MARKETPLACE_TRUSTED_KEYS).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $configured = array_filter((array) config('ecomodular.marketplace.trusted_keys', []), 'is_string');

        return array_values(array_unique([self::OFFICIAL, ...$configured]));
    }
}
