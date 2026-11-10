<?php

namespace ModrixCommerce\CCAvenue;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

final class CCAvenueSettings
{
    public const DEFAULTS = [
        'enabled' => false,
        'test_mode' => true,
        'callbacks_enabled' => true,
        'refunds_enabled' => false,
        'send_billing' => true,
        'send_shipping' => false,
        'merchant_id' => '',
        'label' => 'CCAvenue',
        'currencies' => ['INR'],
        'language' => 'EN',
        'session_minutes' => 30,
    ];

    public const CURRENCIES = ['INR', 'USD', 'SGD', 'GBP', 'EUR'];

    public function __construct(private readonly SettingsRepository $settings, private readonly SecretSettings $secrets) {}

    public function value(string $key): mixed
    {
        return $this->settings->get('ccavenue.'.$key, self::DEFAULTS[$key]);
    }

    public function secret(string $key): ?string
    {
        return $this->secrets->get('ccavenue.'.$key);
    }

    public function ready(): bool
    {
        return $this->value('enabled') && $this->value('merchant_id') !== ''
            && $this->secret('access_code') !== null && $this->secret('working_key') !== null;
    }

    public function checkoutUrl(): string
    {
        return ($this->value('test_mode') ? 'https://test.ccavenue.com' : 'https://secure.ccavenue.com')
            .'/transaction/transaction.do?command=initiateTransaction';
    }

    public function apiUrl(): string
    {
        return ($this->value('test_mode') ? 'https://apitest.ccavenue.com' : 'https://api.ccavenue.com')
            .'/apis/servlet/DoWebTrans';
    }

    public function update(array $data): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $data)) {
                $this->settings->set('ccavenue.'.$key, $data[$key]);
            }
        }
        foreach (['access_code', 'working_key'] as $key) {
            if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                $this->secrets->put('ccavenue.'.$key, $data[$key]);
            }
        }
    }

    public function toArray(): array
    {
        $values = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $values[$key] = $this->value($key);
        }

        return $values + [
            'access_code_saved' => $this->secret('access_code') !== null,
            'working_key_saved' => $this->secret('working_key') !== null,
            'ready' => $this->ready(),
        ];
    }
}
