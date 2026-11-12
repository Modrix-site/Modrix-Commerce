<?php

namespace App\Console\Commands;

use App\Core\Updates\CorePackage;
use App\Core\Updates\CoreUpdateError;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ecomodular:core-package {--output= : Directory (or .zip path) for the package}')]
#[Description('Build a Modrix Commerce core release package from this checkout')]
class CorePackageCommand extends Command
{
    public function handle(CorePackage $package): int
    {
        $root = (string) config('ecomodular.updates.root', base_path());
        $output = (string) ($this->option('output') ?: storage_path('app/private/core-packages'));

        try {
            $zip = $package->build($root, $output);
        } catch (CoreUpdateError $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Built '.$zip);
        $this->line('Size: '.filesize($zip).' bytes, SHA-256: '.hash_file('sha256', $zip));
        $this->line('Build it from a clean checkout after `composer install --no-dev` and `npm run build`.');

        return self::SUCCESS;
    }
}
