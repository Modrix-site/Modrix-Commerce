<?php

namespace EcoModular\PayBy;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for PayBy (Astra Tech).
 *
 * RSA Private Keys are stored encrypted via SecretSettings
 * and are never exposed to client-side bundles.
 */
final class PaybySettings
{
    public const PRIVATE_KEY = 'payby.private_key';

    public const PAYBY_PUBLIC_KEY = 'payby.public_key';

    public const DEFAULT_CURRENCIES = ['AED', 'USD'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('payby.enabled', false);
    }

    public function partnerId(): ?string
    {
        $id = $this->settings->get('payby.partner_id');

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    public function privateKey(): ?string
    {
        return $this->secrets->get(self::PRIVATE_KEY);
    }

    public function paybyPublicKey(): ?string
    {
        return $this->secrets->get(self::PAYBY_PUBLIC_KEY);
    }

    public function testMode(): bool
    {
        return (bool) $this->settings->get('payby.test_mode', true);
    }

    public function label(): string
    {
        $label = $this->settings->get('payby.label');

        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : 'PayBy (Cards, Botim, Apple Pay)';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $raw = $this->settings->get('payby.currencies', self::DEFAULT_CURRENCIES);

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

    public function hasPrivateKey(): bool
    {
        return $this->secrets->has(self::PRIVATE_KEY);
    }

    public function hasPaybyPublicKey(): bool
    {
        return $this->secrets->has(self::PAYBY_PUBLIC_KEY);
    }

    public function ready(): bool
    {
        return $this->enabled()
            && $this->partnerId() !== null
            && $this->hasPrivateKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'partner_id' => $this->partnerId(),
            'has_private_key' => $this->hasPrivateKey(),
            'has_payby_public_key' => $this->hasPaybyPublicKey(),
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
            $this->settings->set('payby.enabled', (bool) $data['enabled']);
        }
        if (array_key_exists('partner_id', $data)) {
            $this->settings->set('payby.partner_id', is_string($data['partner_id']) ? trim($data['partner_id']) : null);
        }
        if (array_key_exists('test_mode', $data)) {
            $this->settings->set('payby.test_mode', (bool) $data['test_mode']);
        }
        if (array_key_exists('label', $data)) {
            $this->settings->set('payby.label', trim((string) $data['label']));
        }
        if (array_key_exists('currencies', $data) && is_array($data['currencies'])) {
            $this->settings->set('payby.currencies', json_encode(array_values(array_unique(array_map('strtoupper', $data['currencies'])))));
        }

        if (! empty($data['private_key']) && is_string($data['private_key'])) {
            $this->secrets->put(self::PRIVATE_KEY, trim($data['private_key']));
        }
        if (array_key_exists('payby_public_key', $data)) {
            if (! empty($data['payby_public_key']) && is_string($data['payby_public_key'])) {
                $this->secrets->put(self::PAYBY_PUBLIC_KEY, trim($data['payby_public_key']));
            } elseif ($data['payby_public_key'] === null || $data['payby_public_key'] === '') {
                $this->secrets->forget(self::PAYBY_PUBLIC_KEY);
            }
        }
    }
}
