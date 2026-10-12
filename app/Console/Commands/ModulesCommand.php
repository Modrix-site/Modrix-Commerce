<?php

namespace App\Console\Commands;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleException;
use App\Core\Extensions\ModuleLoader;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Module lifecycle from the command line. Works in safe mode
 * (ECOMODULAR_SAFE_MODE=true), which makes it the recovery tool when a
 * module prevents the application from booting normally.
 */
#[Signature('ecomodular:modules
    {action=list : list, discover, install, enable, disable, update or uninstall}
    {name? : Module name, e.g. ecomodular/product-reviews}
    {--trust : Confirm that you trust the module code (required to enable)}
    {--confirm= : Repeat the module name to confirm uninstalling (deletes its data)}')]
#[Description('List, discover, install, enable, disable, update or uninstall modules')]
class ModulesCommand extends Command
{
    public function handle(ModuleRepository $repository, ModuleManager $manager): int
    {
        $action = (string) $this->argument('action');
        $name = $this->argument('name');

        if (ModuleLoader::safeMode()) {
            $this->components->warn('Safe mode is on: no module is loaded.');
        }

        if (in_array($action, ['list', 'discover'], true)) {
            $repository->sync();
            $this->table(['Module', 'State', 'Installed', 'On disk', 'Problem'], $repository->all()->map(fn (Extension $e) => [
                $e->name,
                $e->state->label(),
                $e->version ?? '—',
                $e->available_version ?? '—',
                $e->failure ?? implode(' ', (array) $e->errors),
            ])->all());

            return self::SUCCESS;
        }

        if (! is_string($name) || $name === '') {
            $this->components->error('Pass the module name.');

            return self::INVALID;
        }

        try {
            $extension = match ($action) {
                'install' => $manager->install($name),
                'enable' => $manager->enable($name, (bool) $this->option('trust')),
                'disable' => $manager->disable($name),
                'update' => $manager->update($name),
                'uninstall' => $manager->uninstall($name, (string) $this->option('confirm')),
                default => null,
            };
        } catch (ModuleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($extension === null) {
            $this->components->error("Unknown action [{$action}].");

            return self::INVALID;
        }

        $this->components->info("{$extension->name}: {$extension->state->label()}".($extension->version ? " (v{$extension->version})" : '').'.');

        return self::SUCCESS;
    }
}
