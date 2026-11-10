<?php

namespace EcoModular\Tap;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;

/**
 * Merchant configuration. The secret key is stored encrypted and is never
 * sent back to the browser. The key prefix chooses Tap's test or live account.
 */
final class TapSettings
{
    public const SECRET = 'tap.secret_key';

    public const SECRET_PATTERN = '/^sk_(test|live)_[A-Za-z0-9]{10,120}$/';

    public const MERCHANT_PATTERN = '/^[A-Za-z0-9_-]{3,64}$/';

    public const SOURCES = ['card', 'all'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('tap.enabled', false);
    }

    public function secret(): ?string
    {
        $secret = $this->secrets->get(self::SECRET);

        return is_string($secret) && preg_match(self::SECRET_PATTERN, $secret) === 1 ? $secret : null;
    }

    public function live(): bool
    {
        $secret = $this->secret();

        return $secret !== null && str_starts_with($secret, 'sk_live_');
    }

    public function merchantId(): ?string
    {
        $id = $this->settings->get('tap.merchant_id');

        return is_string($id) && preg_match(self::MERCHANT_PATTERN, $id) === 1 ? $id : null;
    }

    /**
     * src_card shows cards on Tap's page. src_all shows every method enabled
     * on the merchant account, including KNET, Benefit and mada.
     */
    public function sourceId(): string
    {
        return $this->settings->get('tap.source', 'all') === 'card' ? 'src_card' : 'src_all';
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->secret() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $secret = $this->secret();

        return [
            'enabled' => $this->enabled(),
            'mode' => $secret === null ? 'unset' : ($this->live() ? 'live' : 'sandbox'),
            'source' => $this->sourceId() === 'src_card' ? 'card' : 'all',
            'merchant_id' => $this->merchantId() ?? '',
            'secret_hint' => $secret === null ? null : $this->secrets->hint(self::SECRET),
            'ready' => $this->ready(),
        ];
    }

    public function update(bool $enabled, string $source, string $merchantId, ?string $secret): void
    {
        $this->settings->setMany([
            'tap.enabled' => $enabled,
            'tap.source' => $source === 'card' ? 'card' : 'all',
            'tap.merchant_id' => $merchantId,
        ]);

        if ($secret !== null && $secret !== '') {
            $this->secrets->put(self::SECRET, $secret);
        }
    }
}
