<?php

namespace EcoModular\ExternalStorage\Console;

use EcoModular\ExternalStorage\Services\MediaOffloadService;
use EcoModular\ExternalStorage\Support\ExternalStorageSettings;
use Illuminate\Console\Command;

class SyncExternalStorageCommand extends Command
{
    protected $signature = 'ecomodular:external-storage:sync {--product= : Optional product ID to sync}';

    protected $description = 'Offload unsynced local product photos and media to the configured external cloud storage provider.';

    public function handle(ExternalStorageSettings $settings, MediaOffloadService $service): int
    {
        if (! $settings->enabled()) {
            $this->warn('External Cloud Storage module is currently disabled in settings.');

            return self::FAILURE;
        }

        if (! $settings->isConfigured()) {
            $this->error("External Cloud Storage provider [{$settings->providerLabel()}] is not fully configured.");

            return self::FAILURE;
        }

        $productId = $this->option('product') !== null ? (int) $this->option('product') : null;
        $this->info("Syncing local media to {$settings->providerLabel()} ({$settings->bucket()})...");

        $result = $service->syncLocalImages($productId);

        $this->info("Completed: {$result['synced']} synced, {$result['failed']} failed.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
