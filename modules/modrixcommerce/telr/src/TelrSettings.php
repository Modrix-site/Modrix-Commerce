<?php

namespace EcoModular\Telr;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration. The authentication key, optional remote key and
 * webhook secret are stored encrypted and never sent to the browser.
 */
final class TelrSettings
{
    public const AUTH_KEY = 'telr.auth_key';

    public const REMOTE_AUTH_KEY = 'telr.remote_auth_key';

    public const WEBHOOK_SECRET = 'telr.webhook_secret';

    public const STORE_ID_PATTERN = '/^[1-9][0-9]{0,11}$/';

    public const SECRET_PATTERN = '/^[^\s]{8,255}$/';

    public const DISPLAYS = ['hosted', 'iframe'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('telr.enabled', false);
    }

    public function storeId(): ?int
    {
        $id = $this->settings->get('telr.store_id');

        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $id = (string) $id;

        return preg_match(self::STORE_ID_PATTERN, $id) === 1 ? (int) $id : null;
    }

    public function testMode(): bool
    {
        return (bool) $this->settings->get('telr.test_mode', true);
    }

    public function display(): string
    {
        $display = $this->settings->get('telr.display', 'hosted');

        return is_string($display) && in_array($display, self::DISPLAYS, true) ? $display : 'hosted';
    }

    public function iframe(): bool
    {
        return $this->display() === 'iframe';
    }

    public function authKey(): ?string
    {
        return $this->secrets->get(self::AUTH_KEY);
    }

    /**
     * Remote API key used for refunds. Falls back to the hosted-page key when
     * Telr issued only one authentication key.
     */
    public function remoteAuthKey(): ?string
    {
        return $this->secrets->get(self::REMOTE_AUTH_KEY) ?? $this->authKey();
    }

    public function webhookSecret(): ?string
    {
        return $this->secrets->get(self::WEBHOOK_SECRET);
    }

    public function hasCredentials(): bool
    {
        return $this->storeId() !== null && $this->authKey() !== null && $this->webhookSecret() !== null;
    }

    /**
     * Ready to take payments: enabled, store id, hosted-page key and the
     * secret that authenticates transaction advice.
     */
    public function ready(): bool
    {
        return $this->enabled() && $this->hasCredentials();
    }

    /**
     * @return array<string, mixed> safe for the admin page (no secrets)
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'store_id' => $this->storeId() === null ? '' : (string) $this->storeId(),
            'test_mode' => $this->testMode(),
            'display' => $this->display(),
            'auth_key_hint' => $this->secrets->hint(self::AUTH_KEY),
            'remote_auth_key_hint' => $this->secrets->hint(self::REMOTE_AUTH_KEY),
            'webhook_secret_hint' => $this->secrets->hint(self::WEBHOOK_SECRET),
            'ready' => $this->ready(),
        ];
    }

    public function update(bool $enabled, string $storeId, bool $testMode, string $display, ?string $authKey, ?string $remoteAuthKey, ?string $webhookSecret): void
    {
        $this->settings->setMany([
            'telr.enabled' => $enabled,
            'telr.store_id' => $storeId,
            'telr.test_mode' => $testMode,
            'telr.display' => $display,
        ]);

        if ($authKey !== null && $authKey !== '') {
            $this->secrets->put(self::AUTH_KEY, $authKey);
        }

        if ($remoteAuthKey !== null && $remoteAuthKey !== '') {
            $this->secrets->put(self::REMOTE_AUTH_KEY, $remoteAuthKey);
        }

        if ($webhookSecret !== null && $webhookSecret !== '') {
            $this->secrets->put(self::WEBHOOK_SECRET, $webhookSecret);
        }
    }
}
