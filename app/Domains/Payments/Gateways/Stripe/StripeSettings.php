<?php

namespace App\Domains\Payments\Gateways\Stripe;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration for Stripe. Keys are stored encrypted (ADR-026).
 */
final class StripeSettings
{
    public const SECRET_KEY = 'stripe.secret_key';

    public const WEBHOOK_SECRET = 'stripe.webhook_secret';

    public const DEFAULT_LABEL = 'Credit or debit card';

    public const KEY_PATTERN = '/^(sk|rk)_(test|live)_[A-Za-z0-9]{10,}$/';

    public const WEBHOOK_SECRET_PATTERN = '/^whsec_[A-Za-z0-9+\/=]{10,}$/';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('payments.stripe.enabled', false);
    }

    public function label(): string
    {
        return (string) ($this->settings->get('payments.stripe.label') ?: self::DEFAULT_LABEL);
    }

    public function applePay(): bool
    {
        return (bool) $this->settings->get('payments.stripe.apple_pay', false);
    }

    public function googlePay(): bool
    {
        return (bool) $this->settings->get('payments.stripe.google_pay', false);
    }

    public function walletConfigurationId(): ?string
    {
        $id = $this->settings->get('payments.stripe.wallet_configuration_id');

        return is_string($id) && preg_match('/^pmc_[A-Za-z0-9]+$/', $id) === 1 ? $id : null;
    }

    public function walletStamp(): string
    {
        return ($this->applePay() ? '1' : '0').($this->googlePay() ? '1' : '0');
    }

    public function rememberedWalletStamp(): ?string
    {
        $stamp = $this->settings->get('payments.stripe.wallet_configuration_stamp');

        return is_string($stamp) ? $stamp : null;
    }

    public function rememberWalletConfiguration(string $id): void
    {
        $this->settings->setMany([
            'payments.stripe.wallet_configuration_id' => $id,
            'payments.stripe.wallet_configuration_stamp' => $this->walletStamp(),
        ]);
    }

    public function secretKey(): ?string
    {
        return $this->secrets->get(self::SECRET_KEY);
    }

    public function webhookSecret(): ?string
    {
        return $this->secrets->get(self::WEBHOOK_SECRET);
    }

    /**
     * "test", "live" or null when no key is stored.
     */
    public function mode(): ?string
    {
        $key = $this->secretKey();

        return $key !== null && preg_match('/^(?:sk|rk)_(test|live)_/', $key, $m) ? $m[1] : null;
    }

    public function hasCredentials(): bool
    {
        return $this->secretKey() !== null && $this->webhookSecret() !== null;
    }

    public function sessionTtlMinutes(): int
    {
        // Stripe accepts 30 minutes to 24 hours.
        return max(30, min(1440, (int) config('services.stripe.session_ttl_minutes', 30)));
    }

    /**
     * @return array{enabled: bool, label: string, mode: string|null, apple_pay: bool, google_pay: bool, secret_key_hint: string|null, webhook_secret_hint: string|null}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'label' => $this->label(),
            'mode' => $this->mode(),
            'apple_pay' => $this->applePay(),
            'google_pay' => $this->googlePay(),
            'secret_key_hint' => $this->secrets->hint(self::SECRET_KEY),
            'webhook_secret_hint' => $this->secrets->hint(self::WEBHOOK_SECRET),
        ];
    }

    public function update(bool $enabled, string $label, ?string $secretKey, ?string $webhookSecret, ?bool $applePay = null, ?bool $googlePay = null): void
    {
        $values = [
            'payments.stripe.enabled' => $enabled,
            'payments.stripe.label' => $label,
        ];

        if ($applePay !== null) {
            $values['payments.stripe.apple_pay'] = $applePay;
        }

        if ($googlePay !== null) {
            $values['payments.stripe.google_pay'] = $googlePay;
        }

        $this->settings->setMany($values);

        if ($secretKey !== null && $secretKey !== '') {
            $this->secrets->put(self::SECRET_KEY, $secretKey);
        }

        if ($webhookSecret !== null && $webhookSecret !== '') {
            $this->secrets->put(self::WEBHOOK_SECRET, $webhookSecret);
        }
    }
}
