<?php

namespace EcoModular\CloudConnector\Console;

use App\Core\Marketplace\Client\StoreLicense;
use EcoModular\CloudConnector\StoreLicenses;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily: refreshes the status of Modrix Cloud licenses on this store and
 * looks for entitled updates. Failures are recorded and shown in the admin;
 * they never disable anything.
 */
#[Signature('modrix:cloud:check')]
#[Description('Check Modrix Cloud licenses and available updates')]
class CloudCheckCommand extends Command
{
    public function handle(StoreLicenses $licenses): int
    {
        foreach (StoreLicense::query()->get() as $license) {
            $before = $license->installedVersion();
            $license = $licenses->check($license);
            $after = $license->installedVersion();
            $updated = $after !== null && $after !== $before ? " updated to v{$after}" : '';
            $this->line("{$license->product_slug}: {$license->status}".($license->last_error ? " ({$license->last_error})" : '').$updated);
        }

        return self::SUCCESS;
    }
}
