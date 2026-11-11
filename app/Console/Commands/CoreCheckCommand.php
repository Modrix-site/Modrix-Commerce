<?php

namespace App\Console\Commands;

use App\Core\Settings\SettingsRepository;
use App\Core\Updates\CoreUpdateCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ecomodular:core-check')]
#[Description('Check Modrix Cloud for a newer platform release')]
class CoreCheckCommand extends Command
{
    public function handle(CoreUpdateCheck $check): int
    {
        $available = $check->run();

        if ($available !== null) {
            $this->info("Modrix Commerce {$available['version']} is available.");
        } elseif (is_string($error = app(SettingsRepository::class)->get('core_update.last_error')) && $error !== '') {
            $this->warn($error);
        } else {
            $this->info('Up to date.');
        }

        return self::SUCCESS;
    }
}
