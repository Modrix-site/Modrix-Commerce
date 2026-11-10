<?php

namespace EcoModular\Instamojo;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for Instamojo Payments.
 *
 * API Keys, Auth Tokens, and Private Salt are stored encrypted through SecretSettings
 * and are never exposed to client-side bundles.
 */
final class InstamojoSettings
{
    public const API_KEY = 'instamojo.api_key';

    public const AUTH_TOKEN = 'instamojo.auth_token';

    public const PRIVATE_SALT = 'instamojo.private_salt';

    public const DEFAULT_CURRENCIES = ['INR'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('instamojo.enabled', false);
    }

    public function apiKey(): ?string
    {
        return $this->secrets->get(self::API_KEY);
    }

    public function authToken(): ?string
    {
        return $this->secrets->get(self::AUTH_TOKEN);
    }

    public function privateSalt(): ?string
    {
        return $this->secrets->get(self::PRIVATE_SALT);
    }

    public function testMode(): bool
    {
        return (bool) $this->settings->get('instamojo.test_mode', true);
    }

    public function label(): string
    {
        $label = $this->settings->get('instamojo.label');

        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : 'Instamojo (Cards, UPI, Net Banking)';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $raw = $this->settings->get('instamojo.currencies', self::DEFAULT_CURRENCIES);

        if (is_string($raw)) {
            $parsed = json_decode($raw, true);
            if (is_array($parsed)) {
                $raw = $parsed;
            } else {
                $raw = array_filter(array_map('trim', explode(',', $raw)));
            }
        }

        if (! is_array($raw) || empty($raw)) {
            return self::DEFAULT_CURRENCIES;
        }

        return array_values(array_unique(array_map('strtoupper', array_map('strval', $raw))));
    }

    public function hasApiKey(): bool
    {
        return $this->secrets->has(self::API_KEY);
    }

    public function hasAuthToken(): bool
    {
        return $this->secrets->has(self::AUTH_TOKEN);
    }

    public function hasPrivateSalt(): bool
    {
        return $this->secrets->has(self::PRIVATE_SALT);
    }

    public function ready(): bool
    {
        return $this->enabled()
            && $this->hasApiKey()
            && $this->hasAuthToken();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'has_api_key' => $this->hasApiKey(),
            'has_auth_token' => $this->hasAuthToken(),
            'has_private_salt' => $this->hasPrivateSalt(),
            'test_mode' => $this->testMode(),
            'label' => $this->label(),
            'currencies' => $this->currencies(),
            'ready' => $this->ready(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        if (array_key_exists('enabled', $data)) {
            $this->settings->set('instamojo.enabled', (bool) $data['enabled']);
        }
        if (array_key_exists('test_mode', $data)) {
            $this->settings->set('instamojo.test_mode', (bool) $data['test_mode']);
        }
        if (array_key_exists('label', $data)) {
            $this->settings->set('instamojo.label', trim((string) $data['label']));
        }
        if (array_key_exists('currencies', $data) && is_array($data['currencies'])) {
            $this->settings->set('instamojo.currencies', json_encode(array_values(array_unique(array_map('strtoupper', $data['currencies'])))));
        }

        if (! empty($data['api_key']) && is_string($data['api_key'])) {
            $this->secrets->put(self::API_KEY, trim($data['api_key']));
        }
        if (! empty($data['auth_token']) && is_string($data['auth_token'])) {
            $this->secrets->put(self::AUTH_TOKEN, trim($data['auth_token']));
        }
        if (array_key_exists('private_salt', $data)) {
            if (! empty($data['private_salt']) && is_string($data['private_salt'])) {
                $this->secrets->put(self::PRIVATE_SALT, trim($data['private_salt']));
            } elseif ($data['private_salt'] === null || $data['private_salt'] === '') {
                $this->secrets->forget(self::PRIVATE_SALT);
            }
        }
    }
}
