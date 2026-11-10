<?php

namespace EcoModular\AuthorizeNet;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration. The transaction key and signature key are stored
 * encrypted and are never sent back to the browser. The API Login ID is not
 * a secret.
 */
final class AuthorizeNetSettings
{
    public const TRANSACTION_KEY = 'authorize_net.transaction_key';

    public const SIGNATURE_KEY = 'authorize_net.signature_key';

    public const LOGIN_PATTERN = '/^[A-Za-z0-9]{8,25}$/';

    public const TRANSACTION_PATTERN = '/^[A-Za-z0-9]{16,128}$/';

    public const SIGNATURE_PATTERN = '/^[A-Fa-f0-9]{64,256}$/';

    public const MODES = ['sandbox', 'live'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('authorize_net.enabled', false);
    }

    public function sandbox(): bool
    {
        return $this->settings->get('authorize_net.mode', 'sandbox') !== 'live';
    }

    public function apiLoginId(): ?string
    {
        $id = $this->settings->get('authorize_net.api_login_id');

        return is_string($id) && preg_match(self::LOGIN_PATTERN, $id) === 1 ? $id : null;
    }

    public function transactionKey(): ?string
    {
        $key = $this->secrets->get(self::TRANSACTION_KEY);

        return is_string($key) && preg_match(self::TRANSACTION_PATTERN, $key) === 1 ? $key : null;
    }

    public function signatureKey(): ?string
    {
        $key = $this->secrets->get(self::SIGNATURE_KEY);

        return is_string($key) && preg_match(self::SIGNATURE_PATTERN, $key) === 1 && strlen($key) % 2 === 0 ? $key : null;
    }

    public function apiUrl(): string
    {
        return AuthorizeNetEndpoint::apiUrl($this->sandbox());
    }

    public function formUrl(): string
    {
        return AuthorizeNetEndpoint::formUrl($this->sandbox());
    }

    public function hasCredentials(): bool
    {
        return $this->apiLoginId() !== null && $this->transactionKey() !== null && $this->signatureKey() !== null;
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->hasCredentials();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'mode' => $this->sandbox() ? 'sandbox' : 'live',
            'api_login_id' => $this->apiLoginId() ?? '',
            'transaction_key_hint' => $this->secrets->hint(self::TRANSACTION_KEY),
            'signature_key_hint' => $this->secrets->hint(self::SIGNATURE_KEY),
            'ready' => $this->ready(),
        ];
    }

    public function update(bool $enabled, string $mode, string $apiLoginId, ?string $transactionKey, ?string $signatureKey): void
    {
        $this->settings->setMany([
            'authorize_net.enabled' => $enabled,
            'authorize_net.mode' => $mode === 'live' ? 'live' : 'sandbox',
            'authorize_net.api_login_id' => $apiLoginId,
        ]);

        if ($transactionKey !== null && $transactionKey !== '') {
            $this->secrets->put(self::TRANSACTION_KEY, $transactionKey);
        }

        if ($signatureKey !== null && $signatureKey !== '') {
            $this->secrets->put(self::SIGNATURE_KEY, $signatureKey);
        }
    }
}
