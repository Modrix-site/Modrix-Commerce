<?php

namespace EcoModular\Paypal;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration. The REST app secret is stored encrypted and is
 * never sent back to the browser. The client id and webhook id are not secrets.
 */
final class PaypalSettings
{
    public const CLIENT_SECRET = 'paypal.client_secret';

    public const CLIENT_PATTERN = '/^[A-Za-z0-9_-]{20,200}$/';

    public const WEBHOOK_PATTERN = '/^[A-Za-z0-9-]{8,50}$/';

    public const MODES = ['sandbox', 'live'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('paypal.enabled', false);
    }

    public function sandbox(): bool
    {
        return $this->settings->get('paypal.mode', 'sandbox') !== 'live';
    }

    public function clientId(): ?string
    {
        $id = $this->settings->get('paypal.client_id');

        return is_string($id) && preg_match(self::CLIENT_PATTERN, $id) === 1 ? $id : null;
    }

    public function webhookId(): ?string
    {
        $id = $this->settings->get('paypal.webhook_id');

        return is_string($id) && preg_match(self::WEBHOOK_PATTERN, $id) === 1 ? $id : null;
    }

    public function clientSecret(): ?string
    {
        return $this->secrets->get(self::CLIENT_SECRET);
    }

    public function apiBase(): string
    {
        return PaypalEndpoint::apiBase($this->sandbox());
    }

    public function hasCredentials(): bool
    {
        return $this->clientId() !== null && $this->clientSecret() !== null && $this->webhookId() !== null;
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
            'mode' => $this->sandbox() ? 'sandbox' : 'live',
            'client_id' => $this->clientId() ?? '',
            'webhook_id' => $this->webhookId() ?? '',
            'client_secret_hint' => $this->secrets->hint(self::CLIENT_SECRET),
            'ready' => $this->ready(),
        ];
    }

    public function update(bool $enabled, string $mode, string $clientId, string $webhookId, ?string $clientSecret): void
    {
        $this->settings->setMany([
            'paypal.enabled' => $enabled,
            'paypal.mode' => $mode === 'live' ? 'live' : 'sandbox',
            'paypal.client_id' => $clientId,
            'paypal.webhook_id' => $webhookId,
        ]);

        if ($clientSecret !== null && $clientSecret !== '') {
            $this->secrets->put(self::CLIENT_SECRET, $clientSecret);
        }
    }
}
