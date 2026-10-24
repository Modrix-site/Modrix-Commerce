<?php

namespace Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Marketplace\Models\User;
use Marketplace\Services\Installer;
use Marketplace\Services\LegacyImporter;
use Throwable;

/** Copies data from the original catalog app (storage/catalog.sqlite). */
class ImportLegacy extends Command
{
    protected $signature = 'marketplace:import-legacy {path=storage/catalog.sqlite : The original catalog database}';

    protected $description = 'Import accounts, packages, licenses, orders, releases and the signing key from the original catalog app';

    public function handle(LegacyImporter $importer, Installer $installer): int
    {
        $path = (string) $this->argument('path');
        $path = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) ? $path : base_path($path);

        try {
            $counts = $importer->import($path);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Kind', 'Rows'], collect($counts)->map(fn ($n, $kind) => [$kind, $n])->values()->all());

        foreach ($importer->warnings as $warning) {
            $this->warn($warning);
        }

        // The old administrators came along, so the web installer is not needed.
        if (User::query()->where('is_admin', true)->exists()) {
            $installer->markInstalled();
        }

        $this->info('Import finished. Stores connected before keep working: the signing key was kept.');

        return self::SUCCESS;
    }
}
