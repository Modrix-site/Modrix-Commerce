<?php

namespace App\Console\Commands;

use App\Core\Updates\CoreUpdater;
use App\Core\Updates\CoreUpdateRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ecomodular:core-update {run : The id of a queued core update run}')]
#[Description('Install a queued Modrix Commerce core update')]
class CoreUpdateCommand extends Command
{
    public function handle(CoreUpdater $updater): int
    {
        $run = CoreUpdateRun::query()->find((int) $this->argument('run'));

        if ($run === null) {
            $this->error('No such core update run.');

            return self::FAILURE;
        }

        $run = $updater->run($run);
        $line = "Core update {$run->from_version} → {$run->to_version}: {$run->status}".($run->message ? " ({$run->message})" : '');

        if ($run->status === CoreUpdateRun::COMPLETED) {
            $this->info($line);

            return self::SUCCESS;
        }

        $this->error($line);

        return self::FAILURE;
    }
}
