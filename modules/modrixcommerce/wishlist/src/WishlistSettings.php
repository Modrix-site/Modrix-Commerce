<?php

namespace EcoModular\Wishlist;

use App\Core\Settings\SettingsRepository;

/**
 * Settings configuration for Wishlist module.
 */
final class WishlistSettings
{
    public const PREFIX = 'wishlist';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::PREFIX . '.enabled', true);
    }

    public function requireLogin(): bool
    {
        return (bool) $this->settings->get(self::PREFIX . '.require_login', false);
    }

    public function allowSharing(): bool
    {
        return (bool) $this->settings->get(self::PREFIX . '.allow_sharing', true);
    }

    public function buttonLabel(): string
    {
        $val = $this->settings->get(self::PREFIX . '.button_label');

        return is_string($val) && trim($val) !== '' ? trim($val) : 'Save to Wishlist';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'require_login' => $this->requireLogin(),
            'allow_sharing' => $this->allowSharing(),
            'button_label' => $this->buttonLabel(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            $this->settings->set(self::PREFIX . '.enabled', (bool) $input['enabled']);
        }

        if (array_key_exists('require_login', $input)) {
            $this->settings->set(self::PREFIX . '.require_login', (bool) $input['require_login']);
        }

        if (array_key_exists('allow_sharing', $input)) {
            $this->settings->set(self::PREFIX . '.allow_sharing', (bool) $input['allow_sharing']);
        }

        if (array_key_exists('button_label', $input)) {
            $this->settings->set(self::PREFIX . '.button_label', trim((string) $input['button_label']));
        }
    }
}
