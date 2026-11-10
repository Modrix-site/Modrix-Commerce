<?php

namespace EcoModular\NGenius;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for Network International (N-Genius).
 *
 * API keys and webhook secrets are stored encrypted via SecretSettings
 * and are never exposed to client-side assets.
 */
final class NGeniusSettings
{
    public const API_KEY = 'ngenius.api_key';

    public const WEBHOOK_SECRET = 'ngenius.webhook_secret';

    public const ACTIONS = ['PURCHASE', 'AUTH'];

    public const DEFAULT_CURRENCIES = ['AED', 'SAR', 'USD', 'EUR', 'GBP', 'OMR', 'BHD', 'KWD', 'QAR'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('ngenius.enabled', false);
    }

    public function outletId(): ?string
    {
        $id = $this->settings->get('ngenius.outlet_id');

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
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
        return (bool) $this->settings->get('ngenius.test_mode', true);
    }

    public function action(): string
    {
        $action = $this->settings->get('ngenius.action', 'PURCHASE');

        return is_string($action) && in_array(strtoupper($action), self::ACTIONS, true)
            ? strtoupper($action)
            : 'PURCHASE';
    }

    public function label(): string
    {
        $label = $this->settings->get('ngenius.label');

        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : 'Network International (N-Genius)';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $raw = $this->settings->get('ngenius.currencies', self::DEFAULT_CURRENCIES);

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
            && $this->outletId() !== null
            && $this->hasApiKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'outlet_id' => $this->outletId(),
            'has_api_key' => $this->hasApiKey(),
            'has_webhook_secret' => $this->hasWebhookSecret(),
            'test_mode' => $this->testMode(),
            'action' => $this->action(),
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
            $this->settings->set('ngenius.enabled', (bool) $data['enabled']);
        }
        if (array_key_exists('outlet_id', $data)) {
            $this->settings->set('ngenius.outlet_id', is_string($data['outlet_id']) ? trim($data['outlet_id']) : null);
        }
        if (array_key_exists('test_mode', $data)) {
            $this->settings->set('ngenius.test_mode', (bool) $data['test_mode']);
        }
        if (array_key_exists('action', $data)) {
            $this->settings->set('ngenius.action', strtoupper((string) $data['action']));
        }
        if (array_key_exists('label', $data)) {
            $this->settings->set('ngenius.label', trim((string) $data['label']));
        }
        if (array_key_exists('currencies', $data) && is_array($data['currencies'])) {
            $this->settings->set('ngenius.currencies', json_encode(array_values(array_unique(array_map('strtoupper', $data['currencies'])))));
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
