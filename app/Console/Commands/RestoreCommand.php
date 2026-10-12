<?php

namespace App\Console\Commands;

use App\Core\Audit\AuditLogger;
use App\Core\Operations\Backups;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Restores a backup made by ecomodular:backup. Every checksum is verified
 * before anything changes; the store is in maintenance mode meanwhile.
 */
#[Signature('ecomodular:restore {backup : File name in the backup directory, or a path} {--verify-only : Check the archive without restoring} {--force : Do not ask for confirmation}')]
#[Description('Restore the database and store files from a backup')]
class RestoreCommand extends Command
{
    public function handle(Backups $backups, AuditLogger $audit): int
    {
        $argument = (string) $this->argument('backup');
        $path = is_file($argument) ? $argument : $backups->directory().'/'.basename($argument);

        try {
            $manifest = $backups->verify($path);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Archive verified: '.count((array) $manifest['files']).' files, made '.$manifest['created_at'].' with version '.$manifest['platform_version'].'.');

        if ($this->option('verify-only')) {
            return self::SUCCESS;
        }

        $this->warn('This replaces the current database, media, private uploads, modules and themes.');
        if (! $this->option('force') && $this->ask('Type "restore" to continue') !== 'restore') {
            $this->line('Cancelled.');

            return self::FAILURE;
        }

        $wasDown = app()->isDownForMaintenance();
        if (! $wasDown) {
            Artisan::call('down', ['--retry' => 60]);
        }

        try {
            $backups->restore($path);
        } catch (Throwable $e) {
            $this->error('Restore failed, nothing was changed: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        } finally {
            if (! $wasDown) {
                Artisan::call('up');
            }
        }

        $audit->record('system.backup_restored', null, ['name' => basename($path), 'made_at' => $manifest['created_at']]);
        $this->info('Restored. Restart queue workers: php artisan queue:restart');

        return self::SUCCESS;
    }
}
