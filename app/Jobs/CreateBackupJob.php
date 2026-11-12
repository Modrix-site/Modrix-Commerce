<?php

namespace App\Jobs;

use App\Core\Audit\AuditLogger;
use App\Core\Operations\BackupOperation;
use App\Core\Operations\Backups;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * "Back up now" from Settings → Backups. Reports through BackupOperation.
 */
class CreateBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly bool $withFiles,
        public readonly string $operationId,
        public readonly int $userId,
    ) {}

    public function timeout(): int
    {
        return (int) config('ecomodular.backup.timeout', 3600);
    }

    public function handle(Backups $backups, BackupOperation $operation, AuditLogger $audit): void
    {
        $operation->update($this->operationId, ['state' => 'running', 'started_at' => now()->toIso8601String()]);

        try {
            $backup = $backups->create($this->withFiles);
        } catch (Throwable $e) {
            report($e);
            $operation->update($this->operationId, ['state' => 'failed', 'finished_at' => now()->toIso8601String(), 'message' => 'Backup failed: '.$e->getMessage()]);

            return;
        }

        $audit->record('system.backup_created', null, ['name' => $backup['name'], 'size' => $backup['size'], 'encrypted' => $backup['encrypted']], User::query()->find($this->userId));
        $operation->update($this->operationId, [
            'state' => 'done',
            'backup' => $backup['name'],
            'finished_at' => now()->toIso8601String(),
            'message' => 'Backup written: '.$backup['name'],
        ]);
    }

    public function failed(?Throwable $e): void
    {
        app(BackupOperation::class)->update($this->operationId, ['state' => 'failed', 'finished_at' => now()->toIso8601String(), 'message' => 'Backup failed: '.($e?->getMessage() ?? 'the worker stopped')]);
    }
}
