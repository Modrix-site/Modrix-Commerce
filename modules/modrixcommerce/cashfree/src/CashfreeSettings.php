<?php

namespace EcoModular\Cashfree;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for Cashfree Payments.
 *
 * Secret credentials (secret key) are stored encrypted via SecretSettings
 * and never sent to client-side assets.
 */
final class CashfreeSettings
{
    public const APP_ID = 'cashfree.app_id';

    public const SECRET_KEY = 'cashfree.secret_key';

    public const DEFAULT_CURRENCIES = ['INR', 'USD'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('cashfree.enabled', false);
    }

    public function appId(): ?string
    {
        $id = $this->settings->get(self::APP_ID);

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    public function secretKey(): ?string
    {
        return $this->secrets->get(self::SECRET_KEY);
    }

    public function testMode(): bool
    {
        return (bool) $this->settings->get('cashfree.test_mode', true);
    }

    public function label(): string
    {
        $label = $this->settings->get('cashfree.label');

        return is_string($label) && trim($label) !== '' ? trim($label) : 'Cashfree Payments (UPI, Cards, Netbanking)';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $currencies = $this->settings->get('cashfree.currencies');

        if (is_array($currencies) && $currencies !== []) {
            return array_values(array_filter($currencies, fn ($c) => is_string($c) && strlen($c) === 3));
        }

        return self::DEFAULT_CURRENCIES;
    }

    public function hasCredentials(): bool
    {
        return $this->appId() !== null && $this->secretKey() !== null;
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->hasCredentials();
    }

    public function update(
        bool $enabled,
        string $appId,
        bool $testMode,
        string $label,
        array $currencies,
        ?string $secretKey = null,
    ): void {
        $this->settings->set('cashfree.enabled', $enabled);
        $this->settings->set(self::APP_ID, trim($appId));
        $this->settings->set('cashfree.test_mode', $testMode);
        $this->settings->set('cashfree.label', trim($label) !== '' ? trim($label) : 'Cashfree Payments');
        $this->settings->set('cashfree.currencies', array_values(array_unique(array_map('strtoupper', $currencies))));

        if ($secretKey !== null && trim($secretKey) !== '') {
            $this->secrets->put(self::SECRET_KEY, trim($secretKey));
        }
    }

    /**
     * @return array<string, mixed> safe for Inertia props
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'app_id' => $this->appId() ?? '',
            'test_mode' => $this->testMode(),
            'label' => $this->label(),
            'currencies' => $this->currencies(),
            'secret_key_hint' => $this->secretKey() !== null ? '••••••••' : null,
            'ready' => $this->ready(),
        ];
    }
}
