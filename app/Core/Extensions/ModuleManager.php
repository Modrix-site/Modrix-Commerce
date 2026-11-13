<?php

namespace App\Core\Extensions;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\Models\Permission;
use App\Core\Authorization\Models\Role;
use App\Core\Authorization\PermissionRegistry;
use App\Core\Authorization\PermissionSynchronizer;
use App\Core\Extensions\Models\Extension;
use App\Models\User;
use Closure;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * The only code that changes a module's lifecycle state (ADR-028).
 *
 * Operations run one at a time under a global lock, re-read the packages on
 * disk first, check the transition and the dependencies, record an
 * `extension_events` row and an audit entry (also for refusals that got past
 * validation and for failures), and clear the boot, route and event caches.
 */
final class ModuleManager
{
    public function __construct(
        private readonly ModuleRepository $repository,
        private readonly ModuleLoader $loader,
        private readonly ModuleSettingsSchema $settings,
        private readonly PermissionRegistry $permissions,
        private readonly PermissionSynchronizer $synchronizer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Runs the module's migrations and registers its permissions. Does not enable it.
     */
    public function install(string $name, ?User $actor = null): Extension
    {
        return $this->operation($name, 'installed', $actor, function (Extension $extension, Manifest $manifest) {
            $this->expect($extension, [ModuleState::Discovered, ModuleState::Failed], 'installed', requireUninstalled: true);
            $this->assertDependencies($manifest, enabled: false);

            try {
                $this->migrate($extension, $manifest);
            } catch (Throwable $e) {
                $rolledBack = $this->rollbackQuietly($extension, $manifest);
                $extension->forceFill([
                    'state' => ModuleState::Failed,
                    'failure' => 'Installation failed: '.$this->describe($e).($rolledBack ? ' Changes were rolled back.' : ' Some changes could not be rolled back; see the recovery guide.'),
                    'failed_at' => now(),
                ])->save();

                throw new ModuleException((string) $extension->failure);
            }

            $this->registerPermissions($manifest);
            $extension->forceFill([
                'state' => ModuleState::Installed,
                'version' => $manifest->version,
                'installed_at' => now(),
                'failure' => null,
                'enabled_before_update' => false,
            ])->save();

            return $extension;
        });
    }

    /**
     * Loads the module's code. Requires explicit confirmation that the
     * administrator trusts the code (it runs with the application's privileges).
     */
    public function enable(string $name, bool $trusted, ?User $actor = null): Extension
    {
        if (! $trusted) {
            throw new ModuleException('Confirm that you trust this module\'s code before enabling it.');
        }

        return $this->operation($name, 'enabled', $actor, function (Extension $extension, Manifest $manifest) {
            $this->expect($extension, [ModuleState::Installed, ModuleState::Disabled, ModuleState::Failed], 'enabled', requireInstalled: true);

            if ($extension->version !== $manifest->version) {
                throw new ModuleException("Update the module to v{$manifest->version} before enabling it.");
            }

            $this->assertDependencies($manifest, enabled: true);
            $this->trialBoot($extension, $manifest);

            $extension->forceFill(['state' => ModuleState::Enabled, 'enabled_at' => now(), 'failure' => null])->save();

            return $extension;
        });
    }

    /**
     * Stops loading the module. All of its data and settings are kept.
     */
    public function disable(string $name, ?User $actor = null): Extension
    {
        return $this->operation($name, 'disabled', $actor, function (Extension $extension) {
            $this->expect($extension, [ModuleState::Enabled, ModuleState::Failed, ModuleState::UpdatePending], 'disabled', requireInstalled: true);

            $dependents = $this->dependents($extension->name, [ModuleState::Enabled]);
            if ($dependents !== []) {
                throw new ModuleException('Disable '.implode(', ', $dependents).' first: they depend on this module.');
            }

            $extension->forceFill([
                'state' => $extension->state === ModuleState::UpdatePending ? ModuleState::UpdatePending : ModuleState::Disabled,
                'enabled_before_update' => false,
            ])->save();

            return $extension;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function configure(string $name, array $input, ?User $actor = null): Extension
    {
        return $this->operation($name, 'configured', $actor, function (Extension $extension, Manifest $manifest) {
            if (! $extension->isInstalled()) {
                throw new ModuleException('Install the module before configuring it.');
            }

            return $extension;
        }, after: fn (Extension $extension, Manifest $manifest) => $this->settings->save($manifest, $input), message: fn () => 'Settings changed: '.implode(', ', array_keys($input)));
    }

    /**
     * Applies newer package files: runs new migrations, updates permissions
     * and restores the previous enabled state.
     */
    public function update(string $name, ?User $actor = null): Extension
    {
        return $this->operation($name, 'updated', $actor, function (Extension $extension, Manifest $manifest) {
            if (! $extension->isInstalled() || ! Comparator::greaterThan($manifest->version, (string) $extension->version)) {
                throw new ModuleException('There is no update to apply.');
            }

            $this->expect($extension, [ModuleState::UpdatePending, ModuleState::Failed], 'updated');
            $this->assertDependencies($manifest, enabled: $extension->enabled_before_update);
            $from = (string) $extension->version;

            try {
                $this->migrate($extension, $manifest);
            } catch (Throwable $e) {
                $extension->forceFill([
                    'state' => ModuleState::Failed,
                    'failure' => "Update to v{$manifest->version} failed: {$this->describe($e)} The module stays at v{$from} and is not loaded. Fix the cause and run the update again.",
                    'failed_at' => now(),
                ])->save();

                throw new ModuleException((string) $extension->failure);
            }

            $this->registerPermissions($manifest);
            Permission::query()->where('source', $manifest->name)
                ->whereNotIn('name', array_column($manifest->permissions, 'name'))
                ->delete();

            $extension->forceFill(['version' => $manifest->version, 'failure' => null]);

            if ($extension->enabled_before_update) {
                try {
                    $this->trialBoot($extension, $manifest);
                    $extension->forceFill(['state' => ModuleState::Enabled, 'enabled_at' => now()]);
                } catch (ModuleException $e) {
                    $extension->forceFill(['state' => ModuleState::Failed, 'failure' => "Updated to v{$manifest->version}, but it failed to start: {$e->getMessage()}", 'failed_at' => now()]);
                }
            } else {
                $extension->state = $extension->enabled_at !== null ? ModuleState::Disabled : ModuleState::Installed;
            }

            $extension->enabled_before_update = false;
            $extension->save();

            return $extension;
        }, message: fn (Extension $e) => "Updated to v{$e->version}.");
    }

    /**
     * Removes the module's tables, settings and permissions. Irreversible:
     * the caller must pass the module's name as confirmation.
     */
    public function uninstall(string $name, string $confirmation, ?User $actor = null): Extension
    {
        if ($confirmation !== $name) {
            throw new ModuleException("Type {$name} to confirm. Uninstalling deletes the module's data.");
        }

        return $this->operation($name, 'uninstalled', $actor, function (Extension $extension, Manifest $manifest) {
            $this->expect($extension, [ModuleState::Installed, ModuleState::Disabled, ModuleState::Failed, ModuleState::UpdatePending], 'uninstalled', requireInstalled: true);

            $dependents = $this->dependents($extension->name, [ModuleState::Installed, ModuleState::Enabled, ModuleState::Disabled, ModuleState::UpdatePending, ModuleState::Failed]);
            if ($dependents !== []) {
                throw new ModuleException('Uninstall '.implode(', ', $dependents).' first: they depend on this module.');
            }

            if ($manifest->migrations !== null) {
                try {
                    $this->migrator()->reset([$this->migrationPath($extension, $manifest)]);
                } catch (Throwable $e) {
                    throw new ModuleException("Could not remove the module's tables: {$this->describe($e)}");
                }
            }

            Permission::query()->where('source', $manifest->name)->delete();
            $this->settings->forget($manifest);

            $extension->forceFill([
                'state' => ModuleState::Discovered,
                'version' => null,
                'installed_at' => null,
                'enabled_at' => null,
                'failure' => null,
                'failed_at' => null,
                'enabled_before_update' => false,
            ])->save();

            return $extension;
        });
    }

    /**
     * Removes a module completely: its tables, settings and permissions (when
     * installed), its package files under the modules directory and its
     * `extensions` record. Irreversible; the caller must pass the module's
     * name as confirmation. The module must not be running, no installed
     * module may require it, and protected first-party modules are refused.
     */
    public function delete(string $name, string $confirmation, ?User $actor = null): void
    {
        if ($confirmation !== $name) {
            throw new ModuleException("Type {$name} to confirm. Deleting removes the module's files and data.");
        }

        if (self::isProtected($name)) {
            throw new ModuleException("{$name} is part of Modrix Commerce and cannot be deleted. Disable it instead.");
        }

        $this->operation($name, 'deleted', $actor, function (Extension $extension, ?Manifest $manifest) {
            if ($extension->state === ModuleState::Enabled
                || (in_array($extension->state, [ModuleState::UpdatePending, ModuleState::Failed], true) && $extension->enabled_before_update)) {
                throw new ModuleException("Disable {$extension->display_name} before deleting it.");
            }

            $dependents = array_values(array_filter(
                $this->dependents($extension->name, ModuleState::cases()),
                fn (string $dependent) => $dependent !== $extension->name,
            ));
            if ($dependents !== []) {
                throw new ModuleException('Uninstall or delete '.implode(', ', $dependents).' first: they depend on this module.');
            }

            $dir = $this->packageDirForDeletion($extension);

            if ($extension->isInstalled()) {
                $manifest ??= $extension->manifest();

                if ($manifest?->migrations !== null && $dir !== null) {
                    try {
                        $this->migrator()->reset([$this->migrationPath($extension, $manifest)]);
                    } catch (Throwable $e) {
                        throw new ModuleException("Could not remove the module's tables: {$this->describe($e)} Nothing was deleted.");
                    }
                }

                Permission::query()->where('source', $extension->name)->delete();

                if ($manifest !== null) {
                    $this->settings->forget($manifest);
                }
            }

            if ($dir !== null && ! File::deleteDirectory($dir)) {
                throw new ModuleException("Could not delete the files in modules/{$extension->path}. Check the folder's permissions and try again.");
            }

            // Drop the vendor folder once its last package is gone.
            $vendor = dirname((string) $dir);
            if ($dir !== null && is_dir($vendor) && (glob($vendor.DIRECTORY_SEPARATOR.'*') ?: []) === []) {
                @rmdir($vendor);
            }

            $extension->delete();
            $extension->state = ModuleState::Discovered;

            return $extension;
        }, allowInvalid: true, message: fn (Extension $e) => "Deleted modules/{$e->path} and its record.");
    }

    /**
     * First-party modules the core relies on; they can be disabled but never deleted.
     */
    public static function isProtected(string $name): bool
    {
        return in_array($name, (array) config('ecomodular.modules.protected', []), true);
    }

    /**
     * The package folder, verified to sit exactly at modules/<vendor>/<name>.
     */
    private function packageDirForDeletion(Extension $extension): ?string
    {
        if (preg_match('#^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$#i', (string) $extension->path) !== 1) {
            throw new ModuleException("Refusing to delete an unexpected package path: {$extension->path}.");
        }

        $dir = realpath(ModuleRepository::packageDir($extension));

        if ($dir === false) {
            return null; // Files already gone; only the record remains.
        }

        if (PackageRoots::rootOf(PackageRoots::MODULES, $dir) === null || is_link(ModuleRepository::packageDir($extension))) {
            throw new ModuleException("Refusing to delete modules/{$extension->path}: it is outside the modules directory.");
        }

        return $dir;
    }

    /**
     * @param  Closure(Extension, Manifest): Extension  $change
     * @param  (Closure(Extension, Manifest): mixed)|null  $after
     * @param  (Closure(Extension): string)|null  $message
     */
    private function operation(string $name, string $action, ?User $actor, Closure $change, ?Closure $after = null, ?Closure $message = null, bool $allowInvalid = false): Extension
    {
        $lock = Cache::lock('ecomodular:modules:lifecycle', 300);

        if (! $lock->block(10)) {
            throw new ModuleException('Another extension operation is in progress. Try again in a moment.');
        }

        try {
            $this->repository->sync($actor);
            $extension = $this->repository->find($name) ?? throw new ModuleException("Module {$name} was not found in the modules directory.");

            // A module built for a newer core: say so plainly, before the
            // generic "invalid manifest" error, for anything that would run it.
            if (in_array($action, ['installed', 'enabled', 'updated'], true)
                && ($needed = CoreCompatibility::updateNeeded($extension)) !== null) {
                $this->repository->record($extension, $action, $extension->state, false, $needed['message'], $actor);

                throw new ModuleException($needed['message']);
            }

            $manifest = $extension->state === ModuleState::Invalid ? null : $extension->manifest();

            if (! $allowInvalid && ($manifest === null || ($extension->errors !== null && $extension->errors !== []))) {
                throw new ModuleException("Module {$name} has an invalid manifest: ".implode(' ', (array) $extension->errors));
            }

            $from = $extension->state;

            try {
                $extension = $change($extension, $manifest);

                if ($after !== null) {
                    $after($extension, $manifest);
                }
            } catch (ModuleException $e) {
                $failed = $extension->fresh() ?? $extension;
                $this->repository->record($failed, $action, $from, false, $e->getMessage(), $actor);
                $this->audit->record("extension.{$action}", null, ['name' => $name, 'success' => false, 'reason' => $e->getMessage()], $actor);

                throw $e;
            }

            $this->repository->record($extension, $action, $from, true, $message === null ? null : $message($extension), $actor);
            $this->audit->record("extension.{$action}", null, ['name' => $name, 'version' => $extension->version, 'success' => true], $actor);

            return $extension;
        } finally {
            ModuleRepository::flushBootCache();
            $this->clearFrameworkCaches();
            $lock->release();
        }
    }

    /**
     * @param  list<ModuleState>  $allowed
     */
    private function expect(Extension $extension, array $allowed, string $action, bool $requireInstalled = false, bool $requireUninstalled = false): void
    {
        $allowedNow = in_array($extension->state, $allowed, true)
            && (! $requireInstalled || $extension->isInstalled())
            && (! $requireUninstalled || ! $extension->isInstalled());

        if (! $allowedNow) {
            throw new ModuleException("{$extension->display_name} cannot be {$action} while it is \"{$extension->state->label()}\".");
        }
    }

    private function assertDependencies(Manifest $manifest, bool $enabled): void
    {
        foreach ($manifest->requires as $dependency => $constraint) {
            $required = $this->repository->find($dependency);
            $states = $enabled ? [ModuleState::Enabled] : [ModuleState::Installed, ModuleState::Enabled, ModuleState::Disabled];

            if ($required === null || ! $required->isInstalled() || ! in_array($required->state, $states, true)) {
                throw new ModuleException("Requires {$dependency} ({$constraint}) to be ".($enabled ? 'enabled' : 'installed').' first.');
            }

            if (! Semver::satisfies((string) $required->version, $constraint)) {
                throw new ModuleException("Requires {$dependency} {$constraint}; v{$required->version} is installed.");
            }
        }
    }

    /**
     * Modules (in the given states) whose manifests require this one.
     *
     * @param  list<ModuleState>  $states
     * @return list<string>
     */
    private function dependents(string $name, array $states): array
    {
        $names = [];

        foreach ($this->repository->all() as $extension) {
            if ($extension->isInstalled() && in_array($extension->state, $states, true)
                && array_key_exists($name, (array) ($extension->manifest['requires'] ?? []))) {
                $names[] = $extension->name;
            }
        }

        return $names;
    }

    private function trialBoot(Extension $extension, Manifest $manifest): void
    {
        if (ModuleLoader::safeMode()) {
            return; // Recovery mode: the module is loaded on the next normal boot.
        }

        // Enabled for the duration of the trial so a failure is recorded against it.
        $previous = $extension->state;
        $extension->forceFill(['state' => ModuleState::Enabled])->save();

        if (! $this->loader->load($extension->name, $extension->path, $manifest)) {
            $error = $this->loader->failure($extension->name);
            $extension->refresh();
            $extension->forceFill(['enabled_before_update' => false])->save();

            throw new ModuleException('The module failed to start: '.($error !== null ? $this->describe($error) : 'unknown error').' It was not enabled.');
        }

        if ($previous !== ModuleState::Enabled) {
            $extension->state = $previous;
        }
    }

    private function migrate(Extension $extension, Manifest $manifest): void
    {
        if ($manifest->migrations === null) {
            return;
        }

        $migrator = $this->migrator();

        if (! $migrator->repositoryExists()) {
            $migrator->getRepository()->createRepository();
        }

        $migrator->run([$this->migrationPath($extension, $manifest)]);
    }

    private function rollbackQuietly(Extension $extension, Manifest $manifest): bool
    {
        if ($manifest->migrations === null) {
            return true;
        }

        try {
            $this->migrator()->reset([$this->migrationPath($extension, $manifest)]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function migrationPath(Extension $extension, Manifest $manifest): string
    {
        return ManifestValidator::resolvePath(ModuleRepository::packageDir($extension), (string) $manifest->migrations, directory: true)
            ?? throw new ModuleException('The module\'s migrations directory is missing.');
    }

    private function migrator(): Migrator
    {
        return app('migrator');
    }

    private function registerPermissions(Manifest $manifest): void
    {
        foreach ($manifest->permissions as $permission) {
            $this->permissions->register($permission['name'], $permission['label'], $permission['group'], $manifest->name);
        }

        $this->synchronizer->sync();

        // Administrators hold every permission (SystemRoles); extend that to new module permissions.
        $administrator = Role::query()->where('name', 'administrator')->where('is_system', true)->first();
        $ids = Permission::query()->whereIn('name', array_column($manifest->permissions, 'name'))->pluck('id')->all();

        if ($administrator !== null && $ids !== []) {
            $administrator->permissions()->syncWithoutDetaching($ids);
        }
    }

    private function clearFrameworkCaches(): void
    {
        try {
            if (app()->routesAreCached()) {
                Artisan::call('route:clear');
            }

            if (app()->eventsAreCached()) {
                Artisan::call('event:clear');
            }
        } catch (Throwable) {
            // Best effort; documented in the recovery guide.
        }
    }

    private function describe(Throwable $e): string
    {
        return Str::limit($e->getMessage() !== '' ? $e->getMessage() : class_basename($e), 500);
    }
}
