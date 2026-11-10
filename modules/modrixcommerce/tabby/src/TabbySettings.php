<?php

namespace EcoModular\Tabby;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use App\Domains\Pricing\Money;

/**
 * Merchant configuration. The secret key and the webhook signing secret are
 * stored encrypted and never sent to the browser (ADR-026); the public key
 * and merchant code are public by design (they are used by Tabby's snippets).
 */
final class TabbySettings
{
    public const SECRET_KEY = 'tabby.secret_key';

    public const WEBHOOK_SECRET = 'tabby.webhook_secret';

    public const SECRET_KEY_PATTERN = '/^sk_(?:test_)?[A-Za-z0-9-]{8,}$/';

    public const PUBLIC_KEY_PATTERN = '/^pk_(?:test_)?[A-Za-z0-9-]{8,}$/';

    public const MERCHANT_CODE_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
        private readonly StoreSettings $store,
        private readonly ModuleSettingsSchema $schema,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('tabby.enabled', false);
    }

    public function publicKey(): ?string
    {
        $key = $this->settings->get('tabby.public_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function merchantCode(): ?string
    {
        $code = $this->settings->get('tabby.merchant_code');

        return is_string($code) && $code !== '' ? $code : null;
    }

    public function secretKey(): ?string
    {
        return $this->secrets->get(self::SECRET_KEY);
    }

    public function webhookSecret(): ?string
    {
        return $this->secrets->get(self::WEBHOOK_SECRET);
    }

    public function webhookId(): ?string
    {
        $id = $this->settings->get('tabby.webhook_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** "test" or "live" from the secret key, null without one. */
    public function mode(): ?string
    {
        $key = $this->secretKey();

        return $key === null ? null : (str_starts_with($key, 'sk_test_') ? 'test' : 'live');
    }

    public function market(): ?TabbyMarket
    {
        return TabbyMarket::for($this->store->currency());
    }

    public function hasCredentials(): bool
    {
        return $this->secretKey() !== null && $this->publicKey() !== null && $this->merchantCode() !== null;
    }

    /**
     * Ready to take payments: enabled, credentials, webhooks and a supported store currency.
     */
    public function ready(): bool
    {
        return $this->enabled() && $this->hasCredentials() && $this->webhookSecret() !== null && $this->market() !== null;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->schema->of(TabbyServiceProvider::MODULE, $key, $default);
    }

    /** Whether the amount is inside the merchant's optional min/max. */
    public function allowsAmount(Money $amount): bool
    {
        $min = (int) $this->option('min_amount', 0);
        $max = (int) $this->option('max_amount', 0);

        return ! $amount->isNegative() && ! $amount->isZero()
            && ($min <= 0 || ! $amount->lessThan(Money::of($min, $amount->currency())))
            && ($max <= 0 || ! $amount->greaterThan(Money::of($max, $amount->currency())));
    }

    /**
     * @return array<string, mixed> safe for the admin page (no secrets)
     */
    public function toArray(): array
    {
        $market = $this->market();

        return [
            'enabled' => $this->enabled(),
            'public_key' => $this->publicKey(),
            'merchant_code' => $this->merchantCode(),
            'secret_key_hint' => $this->secrets->hint(self::SECRET_KEY),
            'mode' => $this->mode(),
            'webhook_registered' => $this->webhookSecret() !== null,
            'currency' => $this->store->currency(),
            'market' => $market?->name(),
            'dashboard' => $market?->dashboard(),
            'ready' => $this->ready(),
        ];
    }

    public function update(bool $enabled, string $publicKey, string $merchantCode, ?string $secretKey): void
    {
        $changedAccount = ($secretKey !== null && $secretKey !== '' && $secretKey !== $this->secretKey())
            || $merchantCode !== $this->merchantCode();

        $this->settings->setMany([
            'tabby.enabled' => $enabled,
            'tabby.public_key' => $publicKey,
            'tabby.merchant_code' => $merchantCode,
        ]);

        if ($secretKey !== null && $secretKey !== '') {
            $this->secrets->put(self::SECRET_KEY, $secretKey);
        }

        // A webhook is registered per merchant code and key environment: a new account needs a new one.
        if ($changedAccount) {
            $this->forgetWebhook();
        }
    }

    public function rememberWebhook(string $id, string $secret): void
    {
        $this->secrets->put(self::WEBHOOK_SECRET, $secret);
        $this->settings->setMany(['tabby.webhook_id' => $id]);
    }

    public function forgetWebhook(): void
    {
        $this->secrets->put(self::WEBHOOK_SECRET, null);
        $this->settings->setMany(['tabby.webhook_id' => null]);
    }
}
