<?php

namespace App\Core\Settings;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;

/**
 * Credentials (API keys, signing secrets) stored encrypted with the
 * application key in the settings table (ADR-026).
 *
 * Secrets are write-only from the admin's point of view: callers get the
 * decrypted value for server-side use, and only `hint()` (the last four
 * characters) may be shown in the UI. If the application key changes, stored
 * secrets cannot be decrypted and read as missing until they are re-entered.
 */
final class SecretSettings
{
    private const PREFIX = 'secrets.';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly Encrypter $encrypter,
    ) {}

    public function get(string $key): ?string
    {
        $stored = $this->settings->get(self::PREFIX.$key);

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return $this->encrypter->decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function put(string $key, ?string $value): void
    {
        $this->settings->set(self::PREFIX.$key, $value === null || $value === '' ? null : $this->encrypter->encryptString($value));
    }

    /**
     * Safe to display: "••••" followed by the last four characters.
     */
    public function hint(string $key): ?string
    {
        $value = $this->get($key);

        return $value === null ? null : '••••'.substr($value, -4);
    }
}
