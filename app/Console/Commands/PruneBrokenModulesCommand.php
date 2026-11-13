<?php

namespace App\Console\Commands;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleException;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Clears out modules this server can no longer run: files missing or partly
 * deleted, or older than the installed version (for example after a deploy
 * removed them). Each one loses its record, files and data, so it can be
 * installed again from Modrix Cloud. Protected modules are never touched.
 */
#[Signature('ecomodular:modules:prune
    {--force : Remove them (without it, only list what would be removed)}')]
#[Description('Remove broken modules (missing, damaged or downgraded files) so they can be reinstalled')]
class PruneBrokenModulesCommand extends Command
{
    public function handle(ModuleRepository $repository, ModuleManager $manager): int
    {
        $repository->sync();

        $broken = $repository->all()
            ->filter(fn (Extension $e) => in_array($e->state, [ModuleState::Failed, ModuleState::Invalid], true))
            ->reject(fn (Extension $e) => ModuleManager::isProtected($e->name))
            ->values();

        if ($broken->isEmpty()) {
            $this->components->info('Nothing to remove: every module is working.');

            return self::SUCCESS;
        }

        $this->table(['Module', 'Installed', 'On disk', 'Problem'], $broken->map(fn (Extension $e) => [
            $e->name,
            $e->version ?? '—',
            $e->available_version ?? '—',
            $e->failure ?? implode(' ', (array) $e->errors),
        ])->all());

        if (! $this->option('force')) {
            $this->components->warn('Nothing was changed. Run again with --force to remove these modules, their files and their data.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($broken as $extension) {
            // It is not running, so the "disable first" guard does not apply.
            $extension->forceFill(['enabled_before_update' => false])->save();

            try {
                $manager->delete($extension->name, $extension->name);
                $this->components->info("Removed {$extension->name}.");
            } catch (ModuleException $e) {
                $failed++;
                $this->components->error("{$extension->name}: {$e->getMessage()}");
            }
        }

        $this->components->info('Install the modules you need again from Modrix Cloud.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
