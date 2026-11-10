<?php

namespace EcoModular\CheckoutCom;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration. The secret key and webhook signature key are stored
 * encrypted and never sent to the browser. The public key is used only by Flow.
 */
final class CheckoutComSettings
{
    public const SECRET_KEY = 'checkout_com.secret_key';

    public const WEBHOOK_SECRET = 'checkout_com.webhook_secret';

    public const SECRET_PATTERN = '/^sk_(sbox_)?[A-Za-z0-9_-]{8,200}$/';

    public const PUBLIC_PATTERN = '/^pk_(sbox_)?[A-Za-z0-9_-]{8,200}$/';

    public const CHANNEL_PATTERN = '/^pc_[A-Za-z0-9]{10,40}$/';

    public const DISPLAYS = ['hosted', 'flow'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('checkout_com.enabled', false);
    }

    public function apiBase(): ?string
    {
        $url = $this->settings->get('checkout_com.api_base');

        return is_string($url) && CheckoutComEndpoint::isApiBase($url) ? rtrim($url, '/') : null;
    }

    public function publicKey(): ?string
    {
        $key = $this->settings->get('checkout_com.public_key');

        return is_string($key) && preg_match(self::PUBLIC_PATTERN, $key) === 1 ? $key : null;
    }

    public function processingChannelId(): ?string
    {
        $id = $this->settings->get('checkout_com.processing_channel_id');

        return is_string($id) && preg_match(self::CHANNEL_PATTERN, $id) === 1 ? $id : null;
    }

    public function display(): string
    {
        $display = $this->settings->get('checkout_com.display', 'hosted');

        return is_string($display) && in_array($display, self::DISPLAYS, true) ? $display : 'hosted';
    }

    public function flow(): bool
    {
        return $this->display() === 'flow';
    }

    public function applePay(): bool
    {
        return (bool) $this->settings->get('checkout_com.apple_pay', false);
    }

    public function googlePay(): bool
    {
        return (bool) $this->settings->get('checkout_com.google_pay', false);
    }

    /**
     * Wallets that Checkout.com should hide for this store.
     *
     * @return list<string>
     */
    public function disabledPaymentMethods(): array
    {
        return array_values(array_filter([
            $this->applePay() ? null : 'applepay',
            $this->googlePay() ? null : 'googlepay',
        ]));
    }

    public function secretKey(): ?string
    {
        return $this->secrets->get(self::SECRET_KEY);
    }

    public function webhookSecret(): ?string
    {
        return $this->secrets->get(self::WEBHOOK_SECRET);
    }

    public function sandbox(): bool
    {
        return str_starts_with((string) $this->secretKey(), 'sk_sbox_');
    }

    public function hasCredentials(): bool
    {
        return $this->secretKey() !== null
            && $this->webhookSecret() !== null
            && $this->apiBase() !== null
            && (! $this->flow() || $this->publicKey() !== null);
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->hasCredentials();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'api_base' => $this->apiBase() ?? '',
            'public_key' => $this->publicKey() ?? '',
            'processing_channel_id' => $this->processingChannelId() ?? '',
            'display' => $this->display(),
            'apple_pay' => $this->applePay(),
            'google_pay' => $this->googlePay(),
            'sandbox' => $this->secretKey() === null ? null : $this->sandbox(),
            'secret_key_hint' => $this->secrets->hint(self::SECRET_KEY),
            'webhook_secret_hint' => $this->secrets->hint(self::WEBHOOK_SECRET),
            'ready' => $this->ready(),
        ];
    }

    public function update(
        bool $enabled,
        string $apiBase,
        string $display,
        string $publicKey,
        string $processingChannelId,
        bool $applePay,
        bool $googlePay,
        ?string $secretKey,
        ?string $webhookSecret,
    ): void {
        $this->settings->setMany([
            'checkout_com.enabled' => $enabled,
            'checkout_com.api_base' => rtrim($apiBase, '/'),
            'checkout_com.display' => $display,
            'checkout_com.public_key' => $publicKey,
            'checkout_com.processing_channel_id' => $processingChannelId,
            'checkout_com.apple_pay' => $applePay,
            'checkout_com.google_pay' => $googlePay,
        ]);

        if ($secretKey !== null && $secretKey !== '') {
            $this->secrets->put(self::SECRET_KEY, $secretKey);
        }

        if ($webhookSecret !== null && $webhookSecret !== '') {
            $this->secrets->put(self::WEBHOOK_SECRET, $webhookSecret);
        }
    }
}
