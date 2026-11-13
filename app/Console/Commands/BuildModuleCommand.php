<?php

namespace App\Console\Commands;

use App\Core\Extensions\ModuleRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Prebuilds a module's screens into its dist/ folder (scripts/build-module.mjs),
 * so stores that install it from Modrix Cloud or a ZIP can show them without
 * rebuilding their own assets. Run before `ecomodular:package` and before
 * uploading a release. Needs Node and the store's npm dependencies.
 */
#[Signature('ecomodular:module:build {module* : vendor/package (in the modules directory) or a path to a module folder}')]
#[Description('Prebuild a module\'s screens (dist/) so it works when installed from Modrix Cloud or a ZIP')]
class BuildModuleCommand extends Command
{
    public function handle(): int
    {
        $dirs = [];

        foreach ((array) $this->argument('module') as $module) {
            $dir = preg_match('#^[a-z0-9-]+/[a-z0-9-]+$#', $module) === 1
                ? ModuleRepository::path().DIRECTORY_SEPARATOR.$module
                : $module;

            if (! is_file($dir.DIRECTORY_SEPARATOR.'module.json')) {
                $this->error("{$module}: no module.json found at {$dir}.");

                return self::FAILURE;
            }

            $dirs[] = $dir;
        }

        $result = Process::path(base_path())
            ->timeout(900)
            ->run(['node', base_path('scripts/build-module.mjs'), ...$dirs], function (string $type, string $output) {
                $this->output->write($output);
            });

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }
}
