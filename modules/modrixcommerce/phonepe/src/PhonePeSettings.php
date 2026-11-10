<?php

namespace EcoModular\PhonePe;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for PhonePe Payment Gateway.
 *
 * Salt Key is stored encrypted using SecretSettings and never exposed to client-side assets.
 */
final class PhonePeSettings
{
    public const MERCHANT_ID = 'phonepe.merchant_id';

    public const SALT_KEY = 'phonepe.salt_key';

    public const SALT_INDEX = 'phonepe.salt_index';

    public const DEFAULT_CURRENCIES = ['INR'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('phonepe.enabled', false);
    }

    public function merchantId(): ?string
    {
        $id = $this->settings->get(self::MERCHANT_ID);

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    public function saltKey(): ?string
    {
        return $this->secrets->get(self::SALT_KEY);
    }

    public function saltIndex(): string
    {
        $index = $this->settings->get(self::SALT_INDEX, '1');

        return is_string($index) && trim($index) !== '' ? trim($index) : '1';
    }

    public function testMode(): bool
    {
        return (bool) $this->settings->get('phonepe.test_mode', true);
    }

    public function label(): string
    {
        $label = $this->settings->get('phonepe.label');

        return is_string($label) && trim($label) !== '' ? trim($label) : 'PhonePe (UPI, QR, Cards, Net Banking)';
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        $currencies = $this->settings->get('phonepe.currencies');

        if (is_array($currencies) && $currencies !== []) {
            return array_values(array_filter($currencies, fn ($c) => is_string($c) && strlen($c) === 3));
        }

        return self::DEFAULT_CURRENCIES;
    }

    public function hasCredentials(): bool
    {
        return $this->merchantId() !== null && $this->saltKey() !== null;
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->hasCredentials();
    }

    public function update(
        bool $enabled,
        string $merchantId,
        bool $testMode,
        string $saltIndex,
        string $label,
        array $currencies,
        ?string $saltKey = null,
    ): void {
        $this->settings->set('phonepe.enabled', $enabled);
        $this->settings->set(self::MERCHANT_ID, trim($merchantId));
        $this->settings->set('phonepe.test_mode', $testMode);
        $this->settings->set(self::SALT_INDEX, trim($saltIndex) !== '' ? trim($saltIndex) : '1');
        $this->settings->set('phonepe.label', trim($label) !== '' ? trim($label) : 'PhonePe');
        $this->settings->set('phonepe.currencies', array_values(array_unique(array_map('strtoupper', $currencies))));

        if ($saltKey !== null && trim($saltKey) !== '') {
            $this->secrets->put(self::SALT_KEY, trim($saltKey));
        }
    }

    /**
     * @return array<string, mixed> safe for Inertia props
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'merchant_id' => $this->merchantId() ?? '',
            'salt_index' => $this->saltIndex(),
            'test_mode' => $this->testMode(),
            'label' => $this->label(),
            'currencies' => $this->currencies(),
            'salt_key_hint' => $this->saltKey() !== null ? '••••••••' : null,
            'ready' => $this->ready(),
        ];
    }
}
