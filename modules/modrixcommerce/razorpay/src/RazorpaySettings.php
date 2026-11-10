<?php

namespace ModrixCommerce\Razorpay;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

final class RazorpaySettings
{
    public const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD', 'MYR', 'JPY', 'KWD', 'BHD', 'OMR'];

    public const METHODS = ['card', 'upi', 'netbanking', 'wallet', 'emi', 'paylater'];

    public const DEFAULTS = [
        'enabled' => false, 'test_mode' => true, 'webhooks_enabled' => true, 'refunds_enabled' => false,
        'capture_authorized' => false, 'prefill_customer' => true, 'retry_enabled' => true,
        'confirm_close' => true, 'auto_open' => true, 'all_methods' => true, 'payment_methods' => ['card', 'upi'],
        'key_id' => '', 'test_key_id' => '', 'label' => 'Razorpay', 'brand_name' => 'Modrix Commerce',
        'theme_color' => '#2563eb', 'currencies' => ['INR'], 'session_minutes' => 30,
    ];

    public function __construct(private readonly SettingsRepository $settings, private readonly SecretSettings $secrets) {}

    public function value(string $key): mixed
    {
        return $this->settings->get('razorpay.'.$key, self::DEFAULTS[$key]);
    }

    public function keyId(?bool $test = null): string
    {
        return (string) $this->value(($test ?? $this->value('test_mode')) ? 'test_key_id' : 'key_id');
    }

    public function secret(string $kind = 'key_secret', ?bool $test = null): ?string
    {
        return $this->secrets->get('razorpay.'.(($test ?? $this->value('test_mode')) ? 'test_' : '').$kind);
    }

    public function ready(): bool
    {
        $prefix = $this->value('test_mode') ? 'rzp_test_' : 'rzp_live_';

        return $this->value('enabled') && str_starts_with($this->keyId(), $prefix) && $this->secret() !== null;
    }

    public function update(array $data): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $data)) {
                $this->settings->set('razorpay.'.$key, $data[$key]);
            }
        }
        foreach (['key_secret', 'test_key_secret', 'webhook_secret', 'test_webhook_secret'] as $key) {
            if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                $this->secrets->put('razorpay.'.$key, $data[$key]);
            }
        }
    }

    public function toArray(): array
    {
        $values = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $values[$key] = $this->value($key);
        }
        foreach (['key_secret' => false, 'test_key_secret' => true, 'webhook_secret' => false, 'test_webhook_secret' => true] as $key => $test) {
            $values[$key.'_saved'] = $this->secret(str_contains($key, 'webhook') ? 'webhook_secret' : 'key_secret', $test) !== null;
        }

        return $values + ['ready' => $this->ready()];
    }
}
