<?php

namespace App\Core\Settings;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Settings → Backups: the daily schedule, its scope and how many archives
 * to keep. Saved in the settings store under backup.*; until something is
 * saved the env values (config ecomodular.backup.*) apply. Reads never
 * fail: without a reachable database (fresh install, package discovery)
 * the config values are used.
 */
final class BackupSettings
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** Daily time "HH:MM" in the app timezone, or "" when off. */
    public function schedule(): string
    {
        return (string) $this->read('backup.schedule', $this->envDefaults()['schedule']);
    }

    public function withFiles(): bool
    {
        return (bool) $this->read('backup.with_files', $this->envDefaults()['with_files']);
    }

    public function keep(): int
    {
        return max(1, min(100, (int) $this->read('backup.keep', $this->envDefaults()['keep'])));
    }

    /**
     * @return array{schedule: string, with_files: bool, keep: int}
     */
    public function envDefaults(): array
    {
        return [
            'schedule' => (string) config('ecomodular.backup.schedule', ''),
            'with_files' => true,
            'keep' => (int) config('ecomodular.backup.keep', 7),
        ];
    }

    /**
     * @param  array{schedule: string, with_files: bool, keep: int}  $values
     */
    public function save(array $values): void
    {
        $this->settings->setMany([
            'backup.schedule' => (string) $values['schedule'],
            'backup.with_files' => (bool) $values['with_files'],
            'backup.keep' => (int) $values['keep'],
        ]);
        $this->settings->flush();
    }

    /** The next scheduled run, or null when the schedule is off. */
    public function nextRun(): ?CarbonImmutable
    {
        $time = $this->schedule();
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            return null;
        }

        $now = CarbonImmutable::now();
        $run = $now->setTime((int) $m[1], (int) $m[2]);

        return $run->lessThanOrEqualTo($now) ? $run->addDay() : $run;
    }

    private function read(string $key, mixed $default): mixed
    {
        try {
            return $this->settings->get($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}
