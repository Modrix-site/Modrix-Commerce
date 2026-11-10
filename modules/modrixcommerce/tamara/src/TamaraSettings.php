<?php

namespace EcoModular\Tamara;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use App\Domains\Pricing\Money;

/**
 * Merchant configuration. The API token and the notification token are stored
 * encrypted and never sent to the browser (ADR-026). Tamara authenticates API
 * calls with the bearer API token and signs webhooks with the notification
 * token; neither is public, so both are secrets.
 */
final class TamaraSettings
{
    public const API_TOKEN = 'tamara.api_token';

    public const NOTIFICATION_TOKEN = 'tamara.notification_token';

    public const ENVIRONMENTS = [TamaraMarket::SANDBOX, TamaraMarket::PRODUCTION];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
        private readonly StoreSettings $store,
        private readonly ModuleSettingsSchema $schema,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('tamara.enabled', false);
    }

    /** "sandbox" or "production". */
    public function environment(): string
    {
        $environment = $this->settings->get('tamara.environment');

        return is_string($environment) && in_array($environment, self::ENVIRONMENTS, true)
            ? $environment
            : TamaraMarket::PRODUCTION;
    }

    public function apiToken(): ?string
    {
        return $this->secrets->get(self::API_TOKEN);
    }

    public function notificationToken(): ?string
    {
        return $this->secrets->get(self::NOTIFICATION_TOKEN);
    }

    public function mode(): string
    {
        return $this->environment();
    }

    public function market(): ?TamaraMarket
    {
        return TamaraMarket::for($this->store->currency());
    }

    public function hasCredentials(): bool
    {
        return $this->apiToken() !== null && $this->notificationToken() !== null;
    }

    /**
     * Ready to take payments: enabled, tokens and a supported store currency.
     */
    public function ready(): bool
    {
        return $this->enabled() && $this->hasCredentials() && $this->market() !== null;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->schema->of(TamaraServiceProvider::MODULE, $key, $default);
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
            'environment' => $this->environment(),
            'api_token_hint' => $this->secrets->hint(self::API_TOKEN),
            'notification_token_hint' => $this->secrets->hint(self::NOTIFICATION_TOKEN),
            'currency' => $this->store->currency(),
            'market' => $market?->name(),
            'dashboard' => $market?->dashboard(),
            'ready' => $this->ready(),
        ];
    }

    public function update(bool $enabled, string $environment, ?string $apiToken, ?string $notificationToken): void
    {
        $this->settings->setMany([
            'tamara.enabled' => $enabled,
            'tamara.environment' => in_array($environment, self::ENVIRONMENTS, true) ? $environment : TamaraMarket::PRODUCTION,
        ]);

        if ($apiToken !== null && $apiToken !== '') {
            $this->secrets->put(self::API_TOKEN, $apiToken);
        }

        if ($notificationToken !== null && $notificationToken !== '') {
            $this->secrets->put(self::NOTIFICATION_TOKEN, $notificationToken);
        }
    }

    public function forgetCredentials(): void
    {
        $this->secrets->put(self::API_TOKEN, null);
        $this->secrets->put(self::NOTIFICATION_TOKEN, null);
    }
}
