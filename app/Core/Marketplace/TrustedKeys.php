<?php

namespace App\Core\Marketplace;

/**
 * Ed25519 public keys trusted to sign what Modrix Cloud serves (packages,
 * licenses and core releases). Configuration only, never an admin setting.
 */
final class TrustedKeys
{
    /**
     * The official Modrix Cloud's (cloud-apps.modrixcommerce.com) Ed25519 public
     * key, key id e6064ae59fe8ad04, which signs every license and release it serves.
     */
    public const OFFICIAL = 'hfNCkhP3lhEjNPjg6Sd5D5s187ebC5ButhVJe+ilKKk=';

    /** First 16 hex digits of sha256 of the raw key, as Modrix Cloud shows it under Settings → Licensing. */
    public const OFFICIAL_KEY_ID = 'e6064ae59fe8ad04';

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
