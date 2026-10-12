<?php

namespace App\Console\Commands;

use App\Core\Marketplace\Client\StoreLicense;
use App\Core\Marketplace\Client\StoreLicenses;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily: refreshes the status of marketplace licenses on this store and
 * looks for entitled updates. Failures are recorded and shown in the admin;
 * they never disable anything.
 */
#[Signature('ecomodular:marketplace:check')]
#[Description('Check marketplace licenses and available updates')]
class MarketplaceCheckCommand extends Command
{
    public function handle(StoreLicenses $licenses): int
    {
        foreach (StoreLicense::query()->get() as $license) {
            $license = $licenses->check($license);
            $this->line("{$license->product_slug}: {$license->status}".($license->last_error ? " ({$license->last_error})" : '').($license->available_release ? ' - update '.($license->available_release['version'] ?? '') : ''));
        }

        return self::SUCCESS;
    }
}
