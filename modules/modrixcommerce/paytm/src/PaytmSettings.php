<?php

namespace EcoModular\Paytm;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for Paytm Payment Gateway.
 *
 * Merchant Secret Key is stored encrypted through SecretSettings
 * and is never exposed to client-side bundles or templates.
 */
final class PaytmSettings
{
    public const MERCHANT_KEY = 'paytm.merchant_key';

    public const DEFAULT_CURRENCIES = ['INR'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('paytm.enabled', false);
    }

    public function mid(): ?string
    {
        $id = $this->settings->get('paytm.mid');

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    public function merchantKey(): ?string
    {
        return $this->secrets->get(self::MERCHANT_KEY);
    }

    public function testMode(): bool
    {
        return (bool) $this->settings->get('paytm.test_mode', true);
    }

    public function website(): string
    {
        $website = $this->settings->get('paytm.website');

        if (is_string($website) && trim($website) !== '') {
            return trim($website);
        }

        return $this->testMode() ? 'WEBSTAGING' : 'DEFAULT';
    }

    public function channelId(): string
    {
        return (string) $this->settings->get('paytm.channel_id', 'WEB');
    }

    public function industryTypeId(): string
    {
        return (string) $this->settings->get('paytm.industry_type_id', 'Retail');
    }

    public function label(): string
    {
        $label = $this->settings->get('paytm.label');

        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : 'Paytm UPI, Wallet & Cards';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $raw = $this->settings->get('paytm.currencies', self::DEFAULT_CURRENCIES);

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

    public function hasMerchantKey(): bool
    {
        return $this->secrets->has(self::MERCHANT_KEY);
    }

    public function ready(): bool
    {
        return $this->enabled()
            && $this->mid() !== null
            && $this->hasMerchantKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'mid' => $this->mid(),
            'has_merchant_key' => $this->hasMerchantKey(),
            'test_mode' => $this->testMode(),
            'website' => $this->website(),
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
            $this->settings->set('paytm.enabled', (bool) $data['enabled']);
        }
        if (array_key_exists('mid', $data)) {
            $this->settings->set('paytm.mid', is_string($data['mid']) ? trim($data['mid']) : null);
        }
        if (array_key_exists('test_mode', $data)) {
            $this->settings->set('paytm.test_mode', (bool) $data['test_mode']);
        }
        if (array_key_exists('website', $data) && is_string($data['website'])) {
            $this->settings->set('paytm.website', trim($data['website']));
        }
        if (array_key_exists('label', $data)) {
            $this->settings->set('paytm.label', trim((string) $data['label']));
        }
        if (array_key_exists('currencies', $data) && is_array($data['currencies'])) {
            $this->settings->set('paytm.currencies', json_encode(array_values(array_unique(array_map('strtoupper', $data['currencies'])))));
        }

        if (! empty($data['merchant_key']) && is_string($data['merchant_key'])) {
            $this->secrets->put(self::MERCHANT_KEY, trim($data['merchant_key']));
        }
    }
}
