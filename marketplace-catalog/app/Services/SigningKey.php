<?php

namespace Marketplace\Services;

use Marketplace\Models\Setting;

/**
 * The marketplace's Ed25519 key pair. Created on first use and stored
 * encrypted in settings; back it up with the database. Stores trust the
 * public key (ECOMODULAR_MARKETPLACE_TRUSTED_KEYS).
 */
class SigningKey
{
    public const SETTING = 'signing_secret';

    private ?string $secret = null;

    public function secret(): string
    {
        if ($this->secret !== null) {
            return $this->secret;
        }

        $stored = Setting::get(self::SETTING);

        if (! is_string($stored) || $stored === '') {
            $stored = Signing::generateKeyPair()['secret'];
            Setting::put(self::SETTING, $stored, secret: true);
        }

        return $this->secret = $stored;
    }

    /** Base64 public key, as stores configure it. */
    public function publicKey(): string
    {
        return Signing::publicKeyFromSecret($this->secret());
    }

    /** The key id carried in every signed token and release. */
    public function keyId(): string
    {
        return Signing::keyId(base64_decode($this->publicKey(), true) ?: '');
    }

    /**
     * Signs data after adding this key's id.
     *
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, signature: string}
     */
    public function sign(array $data): array
    {
        $data['key_id'] = $this->keyId();

        return ['data' => $data, 'signature' => Signing::sign($data, $this->secret())];
    }
}
