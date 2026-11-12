<?php

namespace App\Core\Installation;

use App\Core\Settings\SettingsRepository;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Whether the platform is installed. A lock file answers without a database
 * (a fresh shared-hosting upload has none yet); the settings row remains the
 * record for installs made before the lock file existed.
 */
final class InstallationState
{
    public const KEY = 'system.installed_at';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function isInstalled(): bool
    {
        if (is_file($this->lockFile())) {
            return true;
        }

        try {
            $installedAt = $this->settings->get(self::KEY);
        } catch (Throwable) {
            // No database or no settings table yet: not installed.
            return false;
        }

        if ($installedAt === null) {
            return false;
        }

        // Installed before the lock file existed: write it now, so a later
        // database outage cannot make the store look uninstalled.
        $this->writeLockFile(is_string($installedAt) ? $installedAt : now()->toIso8601String());

        return true;
    }

    public function markInstalled(): void
    {
        $now = now()->toIso8601String();

        $this->settings->set(self::KEY, $now);
        $this->writeLockFile($now);
    }

    private function writeLockFile(string $installedAt): void
    {
        try {
            File::ensureDirectoryExists(dirname($this->lockFile()));
            file_put_contents($this->lockFile(), $installedAt.PHP_EOL, LOCK_EX);
        } catch (Throwable $e) {
            report($e); // The settings row still records the install.
        }
    }

    public function installedAt(): ?string
    {
        try {
            $value = $this->settings->get(self::KEY);
        } catch (Throwable) {
            $value = null;
        }

        if (is_string($value)) {
            return $value;
        }

        return is_file($this->lockFile()) ? (trim((string) file_get_contents($this->lockFile())) ?: null) : null;
    }

    public function lockFile(): string
    {
        return (string) config('ecomodular.installer.lock_file', storage_path('app/installed'));
    }
}
