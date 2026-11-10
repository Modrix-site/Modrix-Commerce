<?php

namespace EcoModular\MyFatoorah;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for MyFatoorah UAE & GCC.
 *
 * API Key and Webhook Secret are stored encrypted through SecretSettings
 * and are never exposed to client-side assets.
 */
final class MyFatoorahSettings
{
    public const API_KEY = 'myfatoorah.api_key';

    public const WEBHOOK_SECRET = 'myfatoorah.webhook_secret';

    public const DEFAULT_CURRENCIES = ['AED', 'SAR', 'KWD', 'BHD', 'QAR', 'OMR', 'USD'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('myfatoorah.enabled', false);
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
        return (bool) $this->settings->get('myfatoorah.test_mode', true);
    }

    public function label(): string
    {
        $label = $this->settings->get('myfatoorah.label');

        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : 'MyFatoorah (Cards, Apple Pay, KNET, Mada)';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $raw = $this->settings->get('myfatoorah.currencies', self::DEFAULT_CURRENCIES);

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
     * @param  array<string, mixed>  $input
     */
    public function save(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            $this->settings->set('myfatoorah.enabled', (bool) $input['enabled']);
        }

        if (array_key_exists('test_mode', $input)) {
            $this->settings->set('myfatoorah.test_mode', (bool) $input['test_mode']);
        }

        if (array_key_exists('label', $input)) {
            $this->settings->set('myfatoorah.label', trim((string) $input['label']));
        }

        if (array_key_exists('currencies', $input) && is_array($input['currencies'])) {
            $this->settings->set('myfatoorah.currencies', json_encode(array_values(array_unique(array_map('strtoupper', $input['currencies'])))));
        }

        if (! empty($input['api_key']) && is_string($input['api_key'])) {
            $this->secrets->put(self::API_KEY, trim($input['api_key']));
        }

        if (! empty($input['webhook_secret']) && is_string($input['webhook_secret'])) {
            $this->secrets->put(self::WEBHOOK_SECRET, trim($input['webhook_secret']));
        }
    }
}
