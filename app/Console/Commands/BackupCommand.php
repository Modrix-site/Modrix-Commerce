<?php

namespace App\Console\Commands;

use App\Core\Audit\AuditLogger;
use App\Core\Operations\Backups;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Creates a backup archive (database dump, media, private uploads, modules
 * and themes) and prunes old ones. Scheduled daily; see
 * docs/operations/backup-and-restore.md.
 */
#[Signature('ecomodular:backup {--database-only : Skip files} {--list : List existing backups}')]
#[Description('Back up the database and store files')]
class BackupCommand extends Command
{
    public function handle(Backups $backups, AuditLogger $audit): int
    {
        if ($this->option('list')) {
            $rows = array_map(fn (array $b) => [$b['name'], number_format($b['size'] / 1024 ** 2, 2).' MB', date('Y-m-d H:i', $b['created_at'])], $backups->all());
            $rows === [] ? $this->info('No backups yet.') : $this->table(['Backup', 'Size', 'Created'], $rows);

            return self::SUCCESS;
        }

        try {
            $backup = $backups->create(withFiles: ! $this->option('database-only'));
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $audit->record('system.backup_created', null, ['name' => $backup['name'], 'size' => $backup['size'], 'encrypted' => $backup['encrypted']]);
        $this->info("Backup written: {$backup['name']} (".number_format($backup['size'] / 1024 ** 2, 2).' MB'.($backup['encrypted'] ? ', AES-256 encrypted' : ', not encrypted').')');

        if (! $backup['encrypted']) {
            $this->warn('Set ECOMODULAR_BACKUP_PASSWORD to encrypt backups, and copy them off this server.');
        }

        return self::SUCCESS;
    }
}
