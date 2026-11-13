<?php

namespace EcoModular\CloudConnector;

use App\Core\Extensions\Manifest;
use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use App\Core\Marketplace\Client\StoreLicense;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use Throwable;

/**
 * Puts back modules and themes whose files are gone while the store still
 * has them installed, typically after a redeploy that replaced the code
 * folder. Each one is downloaded again from Modrix Cloud with its license,
 * verified like any install, and returned to the state it had: an enabled
 * module is enabled again (its update steps run first when Modrix Cloud
 * has a newer version). Nothing the owner uninstalled is brought back,
 * and packages without a Modrix Cloud license are only reported.
 */
final class PackageRestorer
{
    public function __construct(
        private readonly PackageInstaller $installer,
        private readonly ModuleRepository $modules,
        private readonly ThemeRepository $themes,
        private readonly ModuleManager $manager,
    ) {}

    /**
     * @return array{restored: list<string>, failed: array<string, string>, unlicensed: list<string>}
     */
    public function restore(): array
    {
        $result = ['restored' => [], 'failed' => [], 'unlicensed' => []];

        foreach ($this->missing() as $extension) {
            $type = $extension->type === 'theme' ? 'theme' : 'module';
            $license = StoreLicense::query()->where('extension_name', $extension->name)->whereIn('type', [$type, 'free', 'paid'])->first();

            if ($license === null || $license->status !== 'active') {
                $result['unlicensed'][] = $extension->name;

                continue;
            }

            try {
                $this->installer->install($license, null);

                if ($type === 'module') {
                    $this->finishModule($extension->name);
                } else {
                    $this->themes->sync();
                }

                $result['restored'][] = $extension->name;
            } catch (Throwable $e) {
                report($e);
                $result['failed'][$extension->name] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Installed modules and the active theme whose files are not on disk.
     *
     * @return list<Extension>
     */
    public function missing(): array
    {
        $missing = [];

        foreach ($this->modules->all() as $module) {
            if ($module->isInstalled() && ! is_file(ModuleRepository::packageDir($module).DIRECTORY_SEPARATOR.Manifest::FILE)) {
                $missing[] = $module;
            }
        }

        foreach ($this->themes->all() as $theme) {
            $active = $theme->state === ModuleState::Enabled
                || ($theme->state === ModuleState::Failed && $theme->enabled_before_update);

            if ($active && ! is_file(ThemeRepository::packageDir($theme->name).DIRECTORY_SEPARATOR.ThemeManifest::FILE)) {
                $missing[] = $theme;
            }
        }

        return $missing;
    }

    /** Modrix Cloud may hand back a newer version: run its update so the module is usable again. */
    private function finishModule(string $name): void
    {
        $this->modules->sync();
        $module = $this->modules->find($name);

        if ($module !== null && $module->state === ModuleState::UpdatePending) {
            $this->manager->update($name);
        }
    }
}
