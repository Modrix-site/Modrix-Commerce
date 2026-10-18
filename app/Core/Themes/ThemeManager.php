<?php

namespace App\Core\Themes;

use App\Core\Audit\AuditLogger;
use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use App\Core\Marketplace\PackageArchive;
use App\Models\User;
use Closure;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Theme lifecycle: activate, apply update, customize, reset. One operation
 * at a time, each recorded in the lifecycle log and the audit log.
 *
 * Activating applies the theme's current files. For the active theme, newer
 * files become an update that is applied explicitly; merchant customizations
 * are stored separately and survive every operation except an explicit reset.
 */
final class ThemeManager
{
    public function __construct(
        private readonly ThemeRepository $repository,
        private readonly ThemeCustomizations $customizations,
        private readonly ModuleRepository $modules,
        private readonly AuditLogger $audit,
    ) {}

    public function activate(string $name, ?User $actor = null): Extension
    {
        return $this->operation($name, function (Extension $theme) use ($actor) {
            $manifest = $this->validManifest($theme);
            $this->assertRequiredModules($manifest);

            if ($theme->state === ModuleState::Enabled) {
                return $theme;
            }

            DB::transaction(function () use ($theme, $manifest, $actor) {
                $previous = $this->repository->active() ?? Extension::query()->where('type', 'theme')->where('state', ModuleState::Failed)->first();

                if ($previous !== null && $previous->id !== $theme->id) {
                    $from = $previous->state;
                    $previous->forceFill(['state' => $previous->manifest !== null && $previous->errors === null ? ModuleState::Discovered : ModuleState::Invalid, 'enabled_before_update' => false])->save();
                    $this->repository->record($previous, 'deactivated', $from, true, "Replaced by {$theme->name}.", $actor);
                }

                $from = $theme->state;
                $theme->forceFill([
                    'state' => ModuleState::Enabled,
                    'applied_manifest' => $manifest->toArray(),
                    'version' => $manifest->version,
                    'applied_at' => now(),
                    'enabled_at' => now(),
                    'failure' => null,
                ])->save();
                $this->repository->record($theme, 'activated', $from, true, null, $actor);
            });

            $this->audit->record('theme.activated', null, ['name' => $theme->name, 'version' => $manifest->version], $actor);

            return $theme;
        });
    }

    /**
     * Applies newer theme files to the active theme. Customizations are kept;
     * the result reports overrides the new version no longer accepts.
     *
     * @return array{theme: Extension, ignored: list<string>}
     */
    public function applyUpdate(string $name, ?User $actor = null): array
    {
        $ignored = [];

        $theme = $this->operation($name, function (Extension $theme) use ($actor, &$ignored) {
            $manifest = $this->validManifest($theme);

            if ($theme->version === null || ! Comparator::greaterThan($manifest->version, $theme->version)) {
                throw new ThemeException('There is no update to apply.');
            }

            $this->assertRequiredModules($manifest);
            $from = $theme->version;
            $theme->forceFill(['applied_manifest' => $manifest->toArray(), 'version' => $manifest->version, 'applied_at' => now()])->save();

            [, $ignored] = $this->customizations->effectiveDesign($manifest);
            $message = "Updated from v{$from} to v{$manifest->version}. Customizations kept."
                .($ignored !== [] ? ' No longer applicable: '.implode(', ', $ignored).'.' : '');
            $this->repository->record($theme, 'updated', $theme->state, true, $message, $actor);
            $this->audit->record('theme.updated', null, ['name' => $theme->name, 'from' => $from, 'to' => $manifest->version], $actor);

            return $theme;
        });

        return ['theme' => $theme, 'ignored' => $ignored];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function customizeDesign(string $name, array $input, ?User $actor = null): Extension
    {
        return $this->operation($name, function (Extension $theme) use ($input, $actor) {
            $manifest = $this->renderedManifest($theme);
            $before = $this->customizations->designOverrides($manifest);
            $errors = $this->customizations->saveDesign($manifest, $input);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            // The form sends every setting; log only those whose saved value changed.
            $after = $this->customizations->designOverrides($manifest);
            $changed = array_values(array_filter(
                array_unique([...array_keys($before), ...array_keys($after)]),
                fn ($key) => ($before[$key] ?? null) !== ($after[$key] ?? null),
            ));
            $this->repository->record($theme, 'customized', $theme->state, true, 'Design settings changed: '.($changed === [] ? 'none' : implode(', ', $changed)), $actor);
            $this->audit->record('theme.customized', null, ['name' => $theme->name, 'keys' => $changed], $actor);

            return $theme;
        });
    }

    /**
     * @throws ValidationException
     */
    public function customizeSections(string $name, string $page, mixed $sections, ?User $actor = null): Extension
    {
        return $this->operation($name, function (Extension $theme) use ($page, $sections, $actor) {
            $manifest = $this->renderedManifest($theme);
            $errors = $this->customizations->saveSections($manifest, $page, $sections);

            if ($errors !== []) {
                throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($m, $k) => ["sections.{$k}" => $m])->all());
            }

            $this->repository->record($theme, 'customized', $theme->state, true, "Sections of the {$page} page changed.", $actor);
            $this->audit->record('theme.sections_changed', null, ['name' => $theme->name, 'page' => $page], $actor);

            return $theme;
        });
    }

    /**
     * Removes every merchant customization of the theme. Requires the theme name as confirmation.
     */
    public function reset(string $name, string $confirmation, ?User $actor = null): Extension
    {
        if ($confirmation !== $name) {
            throw new ThemeException("Type {$name} to confirm resetting all customizations.");
        }

        return $this->operation($name, function (Extension $theme) use ($actor) {
            $this->customizations->reset($this->renderedManifest($theme));
            $this->repository->record($theme, 'reset', $theme->state, true, 'All customizations removed.', $actor);
            $this->audit->record('theme.reset', null, ['name' => $theme->name], $actor);

            return $theme;
        });
    }

    /**
     * The manifest a theme renders with: the applied snapshot when active, else its files.
     */
    public function disable(string $name, ?User $actor = null): Extension
    {
        return $this->operation($name, function (Extension $theme) use ($actor) {
            $from = $theme->state;
            $theme->forceFill(['state' => ModuleState::Disabled, 'enabled_before_update' => false])->save();
            $this->repository->record($theme, 'disabled', $from, true, null, $actor);
            $this->audit->record('theme.disabled', null, ['name' => $theme->name], $actor);

            return $theme;
        });
    }

    public function delete(string $name, ?User $actor = null): Extension
    {
        return $this->operation($name, function (Extension $theme) use ($actor, $name) {
            if ($theme->state === ModuleState::Enabled) {
                throw new ThemeException('Disable this theme before deleting it.');
            }
            $root = realpath(ThemeRepository::path());
            $dir = realpath(ThemeRepository::packageDir($name));
            if ($root === false || $dir === false || ! str_starts_with($dir, $root.DIRECTORY_SEPARATOR) || is_link(ThemeRepository::packageDir($name))) {
                throw new ThemeException('The theme package directory is unavailable.');
            }
            $backup = storage_path('app/private/theme-archives/'.str_replace('/', '-', $name).'-'.now()->format('YmdHis').'-'.Str::random(8).'.zip');
            File::ensureDirectoryExists(dirname($backup));
            PackageArchive::create($dir, $backup);
            if (! is_file($backup) || filesize($backup) === 0 || ! File::deleteDirectory($dir)) {
                throw new ThemeException('The theme could not be deleted.');
            }
            $this->repository->record($theme, 'deleted', $theme->state, true, 'Package archived; customizations kept.', $actor);
            $this->audit->record('theme.deleted', null, ['name' => $name, 'archive' => basename($backup)], $actor);
            $theme->delete();

            return $theme;
        });
    }

    public function renderedManifest(Extension $theme): ThemeManifest
    {
        $data = $theme->state === ModuleState::Enabled && $theme->applied_manifest !== null ? $theme->applied_manifest : $theme->manifest;

        if ($data === null) {
            throw new ThemeException("{$theme->display_name} has no valid package.");
        }

        return ThemeManifest::fromArray($data);
    }

    /**
     * @param  Closure(Extension): Extension  $change
     */
    private function operation(string $name, Closure $change): Extension
    {
        $lock = Cache::lock('ecomodular:themes:lifecycle', 120);

        if (! $lock->block(10)) {
            throw new ThemeException('Another theme operation is in progress. Try again in a moment.');
        }

        try {
            $this->repository->sync();
            $theme = $this->repository->find($name) ?? throw new ThemeException("Theme {$name} was not found in the themes directory.");

            return $change($theme);
        } finally {
            $lock->release();
        }
    }

    private function validManifest(Extension $theme): ThemeManifest
    {
        if ($theme->manifest === null || ($theme->errors !== null && $theme->errors !== [])) {
            throw new ThemeException("{$theme->display_name} cannot be used: ".implode(' ', (array) $theme->errors));
        }

        return ThemeManifest::fromArray($theme->manifest);
    }

    private function assertRequiredModules(ThemeManifest $manifest): void
    {
        foreach ($manifest->requires as $module => $constraint) {
            $record = $this->modules->find($module);

            if ($record === null || $record->state !== ModuleState::Enabled || ! Semver::satisfies((string) $record->version, $constraint)) {
                throw new ThemeException("This theme requires the module {$module} ({$constraint}) to be enabled.");
            }
        }
    }
}
