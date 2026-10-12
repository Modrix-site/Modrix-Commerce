<?php

namespace App\Core\Installation;

use App\Core\Settings\SettingsRepository;

final class InstallationState
{
    public const KEY = 'system.installed_at';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function isInstalled(): bool
    {
        return $this->settings->get(self::KEY) !== null;
    }

    public function markInstalled(): void
    {
        $this->settings->set(self::KEY, now()->toIso8601String());
    }

    public function installedAt(): ?string
    {
        $value = $this->settings->get(self::KEY);

        return is_string($value) ? $value : null;
    }
}
