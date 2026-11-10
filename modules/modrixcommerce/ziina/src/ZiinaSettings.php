<?php

namespace EcoModular\Ziina;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for Ziina Payment Gateway.
 *
 * API Keys and Webhook Secrets are stored encrypted via SecretSettings
 * and are never sent to client-side bundles.
 */
final class ZiinaSettings
{
    public const API_KEY = 'ziina.api_key';

    public const WEBHOOK_SECRET = 'ziina.webhook_secret';

    public const DEFAULT_CURRENCIES = ['AED', 'USD'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('ziina.enabled', false);
    }

    public function apiKey(): ?string
    {
        return $this->secrets->get(self::API_KEY);
    }

    public function webhookSecret(): ?string
    {
        return $this->secrets->get(self::WEBHOOK_SECRET);
    }

    public function testMode(): bool
    {
        return (bool) $this->settings->get('ziina.test_mode', true);
    }

    public function label(): string
    {
        $label = $this->settings->get('ziina.label');

        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : 'Ziina (Apple Pay, Google Pay, Cards)';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $raw = $this->settings->get('ziina.currencies', self::DEFAULT_CURRENCIES);

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

    public function hasWebhookSecret(): bool
    {
        return $this->secrets->has(self::WEBHOOK_SECRET);
    }

    public function ready(): bool
    {
        return $this->enabled()
            && $this->hasApiKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'has_api_key' => $this->hasApiKey(),
            'has_webhook_secret' => $this->hasWebhookSecret(),
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
            $this->settings->set('ziina.enabled', (bool) $data['enabled']);
        }
        if (array_key_exists('test_mode', $data)) {
            $this->settings->set('ziina.test_mode', (bool) $data['test_mode']);
        }
        if (array_key_exists('label', $data)) {
            $this->settings->set('ziina.label', trim((string) $data['label']));
        }
        if (array_key_exists('currencies', $data) && is_array($data['currencies'])) {
            $this->settings->set('ziina.currencies', json_encode(array_values(array_unique(array_map('strtoupper', $data['currencies'])))));
        }

        if (! empty($data['api_key']) && is_string($data['api_key'])) {
            $this->secrets->put(self::API_KEY, trim($data['api_key']));
        }
        if (array_key_exists('webhook_secret', $data)) {
            if (! empty($data['webhook_secret']) && is_string($data['webhook_secret'])) {
                $this->secrets->put(self::WEBHOOK_SECRET, trim($data['webhook_secret']));
            } elseif ($data['webhook_secret'] === null || $data['webhook_secret'] === '') {
                $this->secrets->forget(self::WEBHOOK_SECRET);
            }
        }
    }
}
