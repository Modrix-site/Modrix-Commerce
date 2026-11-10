<?php

namespace ModrixCommerce\PayTabs;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

final class PayTabsSettings
{
    public const REGIONS = [
        'uae' => ['United Arab Emirates', 'https://secure.paytabs.com'],
        'ksa' => ['Saudi Arabia', 'https://secure.paytabs.sa'],
        'egypt' => ['Egypt', 'https://secure-egypt.paytabs.com'],
        'oman' => ['Oman', 'https://secure-oman.paytabs.com'],
        'jordan' => ['Jordan', 'https://secure-jordan.paytabs.com'],
        'kuwait' => ['Kuwait', 'https://secure-kuwait.paytabs.com'],
        'iraq' => ['Iraq', 'https://secure-iraq.paytabs.com'],
        'morocco' => ['Morocco', 'https://secure-morocco.paytabs.com'],
        'qatar' => ['Qatar', 'https://secure-doha.paytabs.com'],
        'global' => ['Global', 'https://secure-global.paytabs.com'],
    ];

    public const CURRENCIES = ['AED', 'SAR', 'USD', 'EUR', 'GBP', 'EGP', 'OMR', 'JOD', 'KWD', 'IQD', 'MAD', 'QAR', 'BHD'];

    public const METHODS = ['creditcard', 'amex', 'mada', 'unionpay', 'applepay', 'samsungpay', 'stcpay', 'stcpayqr', 'urpay', 'omannet', 'knet', 'knetdebit', 'knetcredit', 'paypal', 'tabby', 'tamara', 'valu', 'aman', 'meezaqr', 'sadad', 'installment'];

    public const DEFAULTS = [
        'enabled' => false, 'test_mode' => true, 'callbacks_enabled' => true, 'refunds_enabled' => false,
        'send_billing' => true, 'send_shipping' => false, 'hide_shipping' => false,
        'all_methods' => true, 'payment_methods' => ['creditcard'],
        'profile_id' => '', 'test_profile_id' => '', 'region' => 'uae',
        'label' => 'PayTabs', 'currencies' => ['AED'], 'language' => 'en', 'session_minutes' => 30,
    ];

    public function __construct(private readonly SettingsRepository $settings, private readonly SecretSettings $secrets) {}

    public function value(string $key): mixed
    {
        return $this->settings->get('paytabs.'.$key, self::DEFAULTS[$key]);
    }

    public function serverKey(?bool $test = null): ?string
    {
        return $this->secrets->get('paytabs.'.(($test ?? $this->value('test_mode')) ? 'test_server_key' : 'server_key'));
    }

    public function profileId(?bool $test = null): string
    {
        return (string) $this->value(($test ?? $this->value('test_mode')) ? 'test_profile_id' : 'profile_id');
    }

    public function ready(): bool
    {
        return $this->value('enabled') && $this->profileId() !== '' && $this->serverKey() !== null;
    }

    public function update(array $data): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $data)) {
                $this->settings->set('paytabs.'.$key, $data[$key]);
            }
        }
        foreach (['server_key', 'test_server_key'] as $key) {
            if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                $this->secrets->put('paytabs.'.$key, $data[$key]);
            }
        }
    }

    public function toArray(): array
    {
        $values = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $values[$key] = $this->value($key);
        }

        return $values + ['server_key_saved' => $this->serverKey(false) !== null, 'test_server_key_saved' => $this->serverKey(true) !== null, 'ready' => $this->ready()];
    }
}
