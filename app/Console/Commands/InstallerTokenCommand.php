<?php

namespace App\Console\Commands;

use App\Core\Installation\InstallationState;
use App\Core\Installation\InstallerToken;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ecomodular:installer-token {--regenerate : Replace the existing token}')]
#[Description('Print the one-time setup token required by the web installer')]
class InstallerTokenCommand extends Command
{
    public function handle(InstallationState $state, InstallerToken $token): int
    {
        if ($state->isInstalled()) {
            $token->delete();
            $this->components->error('Modrix Commerce is already installed; the web installer is disabled.');

            return self::FAILURE;
        }

        $value = $this->option('regenerate') ? $token->regenerate() : $token->ensure();

        $this->components->info('Web installer setup token:');
        $this->line($value);

        return self::SUCCESS;
    }
}
