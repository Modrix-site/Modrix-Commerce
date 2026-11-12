<?php

namespace App\Jobs;

use App\Core\Audit\AuditLogger;
use App\Core\Operations\BackupOperation;
use App\Core\Operations\Backups;
use App\Core\Settings\SettingsRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Restore from Settings → Backups. The request already verified the
 * archive. Order: database-only safety backup, maintenance mode (with a
 * bypass secret for the admin watching), Backups::restore(), drop jobs the
 * dump brought back, back up. If anything fails once the restore has
 * started, the store stays in maintenance and the status carries the
 * recovery commands.
 */
class RestoreBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $name,
        public readonly string $operationId,
        public readonly int $userId,
        /** Maintenance bypass secret; the request generates it so the page can keep polling. */
        public readonly ?string $secret = null,
    ) {}

    public function timeout(): int
    {
        return (int) config('ecomodular.backup.timeout', 3600);
    }

    public function handle(Backups $backups, BackupOperation $operation, AuditLogger $audit): void
    {
        $operation->update($this->operationId, ['state' => 'running', 'started_at' => now()->toIso8601String()]);
        $path = $backups->directory().'/'.basename($this->name);
        $preRestore = null;

        // 1-2: nothing has changed yet; a failure here leaves the store as it was.
        try {
            $preRestore = $backups->create(withFiles: false, prune: false)['name'];
            $secret = $this->secret ?? Str::random(32);
            Artisan::call('down', ['--secret' => $secret, '--retry' => 60]);
            $operation->update($this->operationId, ['pre_restore' => $preRestore, 'secret' => $secret]);
        } catch (Throwable $e) {
            report($e);
            Artisan::call('up');
            $this->markFailed($operation, $audit, 'Restore not started: '.$e->getMessage(), $preRestore, recovery: []);

            return;
        }

        // 3-5: the restore itself. On failure the store stays down.
        try {
            $backups->restore($path);

            // Jobs queued when the backup was made must not run again, and
            // cached settings belong to the replaced database.
            if (Schema::hasTable('jobs')) {
                DB::table('jobs')->delete();
            }
            app(SettingsRepository::class)->flush();
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($operation, $audit, 'Restore failed: '.$e->getMessage(), $preRestore, recovery: [
                "php artisan ecomodular:restore {$preRestore} --force",
                'php artisan up',
            ]);

            return;
        }

        Artisan::call('up');
        // Workers (this one included) restart after their current job, on the restored state.
        Artisan::call('queue:restart');
        $audit->record('system.backup_restored', null, ['name' => $this->name, 'pre_restore' => $preRestore, 'user_id' => $this->userId]);
        $operation->update($this->operationId, [
            'state' => 'done',
            'secret' => null,
            'finished_at' => now()->toIso8601String(),
            'message' => "Restored {$this->name}. Sign in with the accounts as they were in that backup.",
        ]);
    }

    public function failed(?Throwable $e): void
    {
        app(BackupOperation::class)->update($this->operationId, [
            'state' => 'failed',
            'finished_at' => now()->toIso8601String(),
            'message' => 'Restore stopped: '.($e?->getMessage() ?? 'the worker stopped').'. Check whether the store is in maintenance mode.',
            'recovery' => ['php artisan ecomodular:backup --list', 'php artisan ecomodular:restore <pre-restore backup> --force', 'php artisan up'],
        ]);
    }

    /** @param  list<string>  $recovery */
    private function markFailed(BackupOperation $operation, AuditLogger $audit, string $message, ?string $preRestore, array $recovery): void
    {
        try {
            $audit->record('system.backup_restore_failed', null, ['name' => $this->name, 'pre_restore' => $preRestore, 'user_id' => $this->userId, 'error' => Str::limit($message, 500)]);
        } catch (Throwable $e) {
            report($e); // The database may be the thing that broke.
        }

        $operation->update($this->operationId, [
            'state' => 'failed',
            'finished_at' => now()->toIso8601String(),
            'message' => $message,
            'recovery' => $recovery,
        ]);
    }
}
