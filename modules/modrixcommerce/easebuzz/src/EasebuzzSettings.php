<?php

namespace EcoModular\Easebuzz;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for Easebuzz India.
 *
 * Merchant Salt is stored encrypted through SecretSettings
 * and is never exposed to client-side assets.
 */
final class EasebuzzSettings
{
    public const SALT = 'easebuzz.salt';

    public const DEFAULT_CURRENCIES = ['INR'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('easebuzz.enabled', false);
    }

    public function merchantKey(): ?string
    {
        $key = $this->settings->get('easebuzz.merchant_key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    public function salt(): ?string
    {
        return $this->secrets->get(self::SALT);
    }

    public function testMode(): bool
    {
        return (bool) $this->settings->get('easebuzz.test_mode', true);
    }

    public function label(): string
    {
        $label = $this->settings->get('easebuzz.label');

        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : 'Easebuzz (Cards, UPI, Net Banking)';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $raw = $this->settings->get('easebuzz.currencies', self::DEFAULT_CURRENCIES);

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
     * @param  array<string, mixed>  $input
     */
    public function save(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            $this->settings->set('easebuzz.enabled', (bool) $input['enabled']);
        }

        if (array_key_exists('merchant_key', $input)) {
            $this->settings->set('easebuzz.merchant_key', is_string($input['merchant_key']) ? trim($input['merchant_key']) : null);
        }

        if (array_key_exists('test_mode', $input)) {
            $this->settings->set('easebuzz.test_mode', (bool) $input['test_mode']);
        }

        if (array_key_exists('label', $input)) {
            $this->settings->set('easebuzz.label', trim((string) $input['label']));
        }

        if (array_key_exists('currencies', $input) && is_array($input['currencies'])) {
            $this->settings->set('easebuzz.currencies', json_encode(array_values(array_unique(array_map('strtoupper', $input['currencies'])))));
        }

        if (! empty($input['salt']) && is_string($input['salt'])) {
            $this->secrets->put(self::SALT, trim($input['salt']));
        }
    }
}
