<?php

namespace EcoModular\CloudConnector\Console;

use EcoModular\CloudConnector\PackageRestorer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Reinstalls, from Modrix Cloud, installed modules and the active theme whose
 * files are missing (after a redeploy, for example). Runs after every
 * `migrate` and every five minutes; safe to run any time.
 */
#[Signature('modrix:cloud:restore')]
#[Description('Reinstall from Modrix Cloud the installed modules and theme whose files are missing')]
class CloudRestoreCommand extends Command
{
    public function handle(PackageRestorer $restorer): int
    {
        $result = $restorer->restore();

        foreach ($result['restored'] as $name) {
            $this->info("{$name}: restored from Modrix Cloud.");
        }

        foreach ($result['failed'] as $name => $reason) {
            $this->error("{$name}: {$reason}");
        }

        foreach ($result['unlicensed'] as $name) {
            $this->warn("{$name}: files missing and no active Modrix Cloud license on this store; upload it again or restore a backup.");
        }

        if ($result === ['restored' => [], 'failed' => [], 'unlicensed' => []]) {
            $this->line('Nothing to restore.');
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
