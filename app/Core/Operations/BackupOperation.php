<?php

namespace App\Core\Operations;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The one backup or restore started from the admin at a time, tracked in
 * a JSON file in the backup directory. That directory is never backed up
 * nor replaced by a restore, so the status survives the database being
 * swapped underneath it. A queued/running operation older than the backup
 * timeout counts as failed, so a crashed worker never blocks the page.
 *
 * Status keys: id, type (backup|restore), state (queued|running|done|failed),
 * backup, with_files, user_id, queued_at, started_at, finished_at, message,
 * and for restores pre_restore, secret, recovery.
 */
final class BackupOperation
{
    public function __construct(private readonly Backups $backups) {}

    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        $status = $this->read();
        if ($status === null) {
            return null;
        }

        if (in_array($status['state'] ?? null, ['queued', 'running'], true)) {
            $since = $status['started_at'] ?? $status['queued_at'] ?? null;
            $timeout = max(60, (int) config('ecomodular.backup.timeout', 3600));
            if ($since === null || Carbon::parse($since)->addSeconds($timeout)->isPast()) {
                $status['state'] = 'failed';
                $status['message'] = 'Timed out: no worker finished it.';
            }
        }

        return $status;
    }

    public function busy(): bool
    {
        return in_array($this->current()['state'] ?? null, ['queued', 'running'], true);
    }

    /**
     * @return array<string, mixed> The new status.
     *
     * @throws OperationInProgress
     */
    public function begin(string $type, ?string $backup, bool $withFiles, int $userId): array
    {
        return $this->locked(function () use ($type, $backup, $withFiles, $userId) {
            if ($this->busy()) {
                throw new OperationInProgress('Another backup or restore is in progress.');
            }

            $status = [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'state' => 'queued',
                'backup' => $backup,
                'with_files' => $withFiles,
                'user_id' => $userId,
                'queued_at' => now()->toIso8601String(),
                'started_at' => null,
                'finished_at' => null,
                'message' => null,
            ];
            $this->write($status);

            return $status;
        });
    }

    /**
     * Merges changes into the status, if it still belongs to this operation.
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(string $id, array $changes): void
    {
        $this->locked(function () use ($id, $changes) {
            $status = $this->read();
            if (($status['id'] ?? null) === $id) {
                $this->write([...$status, ...$changes]);
            }
        });
    }

    /** @return array<string, mixed>|null */
    private function read(): ?array
    {
        $file = $this->file();
        if (! is_file($file)) {
            return null;
        }

        $status = json_decode((string) @file_get_contents($file), true);

        return is_array($status) ? $status : null;
    }

    /** @param  array<string, mixed>  $status */
    private function write(array $status): void
    {
        File::put($this->file(), (string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($this->file(), 0600);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(callable $callback): mixed
    {
        File::ensureDirectoryExists($this->backups->directory(), 0700);
        $handle = fopen($this->backups->directory().'/.operation.lock', 'c');
        if ($handle === false) {
            return $callback();
        }

        try {
            flock($handle, LOCK_EX);

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function file(): string
    {
        return $this->backups->directory().'/.operation.json';
    }
}
