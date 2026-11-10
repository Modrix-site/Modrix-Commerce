<?php

namespace EcoModular\PayU;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for PayU India.
 *
 * Merchant Salt is stored encrypted through SecretSettings
 * and is never exposed to client-side assets.
 */
final class PayuSettings
{
    public const SALT = 'payu.salt';

    public const DEFAULT_CURRENCIES = ['INR'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('payu.enabled', false);
    }

    public function merchantKey(): ?string
    {
        $key = $this->settings->get('payu.merchant_key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    public function salt(): ?string
    {
        return $this->secrets->get(self::SALT);
    }

    public function testMode(): bool
    {
        return (bool) $this->settings->get('payu.test_mode', true);
    }

    public function label(): string
    {
        $label = $this->settings->get('payu.label');

        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : 'PayU (Cards, UPI, Net Banking)';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $raw = $this->settings->get('payu.currencies', self::DEFAULT_CURRENCIES);

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

    public function hasSalt(): bool
    {
        return $this->secrets->has(self::SALT);
    }

    public function ready(): bool
    {
        return $this->enabled()
            && $this->merchantKey() !== null
            && $this->hasSalt();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'merchant_key' => $this->merchantKey(),
            'has_salt' => $this->hasSalt(),
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
            $this->settings->set('payu.enabled', (bool) $data['enabled']);
        }
        if (array_key_exists('merchant_key', $data)) {
            $this->settings->set('payu.merchant_key', is_string($data['merchant_key']) ? trim($data['merchant_key']) : null);
        }
        if (array_key_exists('test_mode', $data)) {
            $this->settings->set('payu.test_mode', (bool) $data['test_mode']);
        }
        if (array_key_exists('label', $data)) {
            $this->settings->set('payu.label', trim((string) $data['label']));
        }
        if (array_key_exists('currencies', $data) && is_array($data['currencies'])) {
            $this->settings->set('payu.currencies', json_encode(array_values(array_unique(array_map('strtoupper', $data['currencies'])))));
        }

        if (! empty($data['salt']) && is_string($data['salt'])) {
            $this->secrets->put(self::SALT, trim($data['salt']));
        }
    }
}
