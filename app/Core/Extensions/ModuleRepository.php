<?php

namespace App\Core\Extensions;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\PermissionRegistry;
use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\Models\ExtensionEvent;
use App\Models\User;
use Composer\Semver\Comparator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

/**
 * Discovers packages under the modules directory and keeps the
 * `extensions` table in step with them (ADR-028).
 *
 * Discovery reads and validates manifests only. It never installs, enables
 * or loads anything: those are explicit ModuleManager operations.
 */
final class ModuleRepository
{
    public const BOOT_CACHE_KEY = 'ecomodular.modules.enabled';

    public function __construct(
        private readonly ManifestValidator $validator,
        private readonly PermissionRegistry $permissions,
    ) {}

    public static function path(): string
    {
        return rtrim((string) config('ecomodular.modules.path', base_path('modules')), '/\\');
    }

    public static function packageDir(Extension|string $extension): string
    {
        $relative = $extension instanceof Extension ? $extension->path : $extension;

        return self::path().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * @return Collection<int, Extension>
     */
    public function all(): Collection
    {
        return Extension::query()->where('type', 'module')->orderBy('name')->get();
    }

    public function find(string $name): ?Extension
    {
        return Extension::query()->where('name', $name)->first();
    }

    /**
     * Rescans the modules directory and updates every record.
     */
    public function sync(?User $actor = null): void
    {
        $scanned = $this->scan();
        $context = $this->context($scanned);

        /** @var array<string, ValidationResult> $results */
        $results = [];
        foreach ($scanned as $name => $package) {
            $results[$name] = $package['error'] !== null
                ? ValidationResult::invalid([$package['error']])
                : $this->validator->validate($package['dir'], $name, $package['data'], $context);
        }

        $results = $this->rejectCycles($results);

        DB::transaction(function () use ($scanned, $results, $actor) {
            foreach ($results as $name => $result) {
                $this->apply($name, $result, $actor);
            }

            foreach ($this->all() as $extension) {
                if (! isset($scanned[$extension->name])) {
                    $this->missing($extension, $actor);
                }
            }
        });

        self::flushBootCache();
    }

    /**
     * Enabled modules with the manifest snapshot they were enabled with.
     *
     * @return list<array{name: string, path: string, version: string, manifest: array<string, mixed>}>
     */
    public function enabledForBoot(): array
    {
        /** @var list<array{name: string, path: string, version: string, manifest: array<string, mixed>}> */
        return Cache::rememberForever(self::BOOT_CACHE_KEY, fn () => Extension::query()
            ->where('type', 'module')
            ->where('state', ModuleState::Enabled)
            ->whereNotNull('manifest')
            ->orderBy('name')
            ->get()
            ->map(fn (Extension $e) => ['name' => $e->name, 'path' => $e->path, 'version' => (string) $e->version, 'manifest' => (array) $e->manifest])
            ->all());
    }

    public static function flushBootCache(): void
    {
        Cache::forget(self::BOOT_CACHE_KEY);
    }

    /**
     * Version of the files on disk, read without full validation (boot-time check).
     */
    public static function diskVersion(string $relativePath): ?string
    {
        $file = self::packageDir($relativePath).DIRECTORY_SEPARATOR.Manifest::FILE;

        if (! is_file($file) || filesize($file) > Manifest::MAX_BYTES) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : null;
    }

    /**
     * Takes a module that failed while loading out of service. Never throws:
     * it runs while the application is booting.
     */
    public function quarantine(string $name, Throwable $e): void
    {
        $message = Str::limit(class_basename($e).': '.$e->getMessage(), 1000);
        Log::error("Module {$name} failed while loading and was disabled.", ['exception' => $e]);

        try {
            $extension = $this->find($name);

            if ($extension !== null && $extension->state === ModuleState::Enabled) {
                $extension->enabled_before_update = true;
                $extension->forceFill(['state' => ModuleState::Failed, 'failure' => $message, 'failed_at' => now()])->save();
                $this->record($extension, 'quarantined', ModuleState::Enabled, false, $message);
                app(AuditLogger::class)->record('extension.quarantined', null, ['name' => $name, 'error' => $message]);
            }

            self::flushBootCache();
        } catch (Throwable $inner) {
            Log::critical("Could not quarantine module {$name}.", ['exception' => $inner]);
        }
    }

    public function record(Extension $extension, string $action, ?ModuleState $from, bool $success = true, ?string $message = null, ?User $actor = null): void
    {
        ExtensionEvent::query()->forceCreate([
            'extension' => $extension->name,
            'action' => $action,
            'from_state' => $from?->value,
            'to_state' => $extension->state->value,
            'version' => $extension->version ?? $extension->available_version,
            'success' => $success,
            'message' => $message === null ? null : mb_substr($message, 0, 2000),
            'actor_id' => $actor?->id,
        ]);
    }

    /**
     * @return array<string, array{dir: string, data: mixed, error: string|null}>
     */
    private function scan(): array
    {
        $packages = [];

        foreach (glob(self::path().'/*/*/'.Manifest::FILE) ?: [] as $file) {
            $dir = dirname($file);
            $name = basename(dirname($dir)).'/'.basename($dir);

            if (filesize($file) > Manifest::MAX_BYTES) {
                $packages[$name] = ['dir' => $dir, 'data' => null, 'error' => 'module.json is larger than 64 KB.'];

                continue;
            }

            try {
                $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
                $packages[$name] = ['dir' => $dir, 'data' => $data, 'error' => null];
            } catch (JsonException $e) {
                $packages[$name] = ['dir' => $dir, 'data' => null, 'error' => "module.json is not valid JSON: {$e->getMessage()}."];
            }
        }

        ksort($packages);

        return $packages;
    }

    /**
     * First claim wins (alphabetical), so conflicts are reported on the later package.
     *
     * @param  array<string, array{dir: string, data: mixed, error: string|null}>  $scanned
     */
    private function context(array $scanned): ValidationContext
    {
        $namespaces = [];
        $permissions = [];
        $migrations = [];

        foreach ($this->permissions->all() as $definition) {
            if ($definition['source'] === 'core') {
                $permissions[$definition['name']] = 'core';
            }
        }

        foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
            $migrations[basename($file)] = 'core';
        }

        foreach ($scanned as $name => $package) {
            $data = is_array($package['data']) ? $package['data'] : [];

            foreach (array_keys((array) ($data['autoload']['psr-4'] ?? [])) as $namespace) {
                $namespaces[(string) $namespace] ??= $name;
            }

            foreach ((array) ($data['permissions'] ?? []) as $permission) {
                if (is_array($permission) && is_string($permission['name'] ?? null)) {
                    $permissions[$permission['name']] ??= $name;
                }
            }

            if (is_string($data['migrations'] ?? null)) {
                $dir = ManifestValidator::resolvePath($package['dir'], $data['migrations'], directory: true);

                foreach ($dir === null ? [] : (glob($dir.'/*.php') ?: []) as $file) {
                    $migrations[basename($file)] ??= $name;
                }
            }
        }

        return new ValidationContext($namespaces, $permissions, $migrations);
    }

    /**
     * @param  array<string, ValidationResult>  $results
     * @return array<string, ValidationResult>
     */
    private function rejectCycles(array $results): array
    {
        $graph = [];
        foreach ($results as $name => $result) {
            if ($result->manifest !== null) {
                $graph[$name] = array_keys($result->manifest->requires);
            }
        }

        foreach (array_keys($graph) as $start) {
            $cycle = $this->findCycle($start, $graph, [$start]);

            if ($cycle !== null) {
                $results[$start] = ValidationResult::invalid(['Dependency cycle: '.implode(' → ', $cycle).'.']);
            }
        }

        return $results;
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @param  list<string>  $path
     * @return list<string>|null
     */
    private function findCycle(string $node, array $graph, array $path): ?array
    {
        foreach ($graph[$node] ?? [] as $next) {
            if ($next === $path[0]) {
                return [...$path, $next];
            }

            if (! in_array($next, $path, true) && count($path) < 50) {
                $found = $this->findCycle($next, $graph, [...$path, $next]);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function apply(string $name, ValidationResult $result, ?User $actor): void
    {
        $extension = $this->find($name) ?? new Extension;
        $isNew = ! $extension->exists;
        $from = $isNew ? null : $extension->state;
        $manifest = $result->manifest;

        $extension->forceFill([
            'name' => $name,
            'type' => 'module',
            'path' => $name,
            'display_name' => $manifest->displayName ?? ($extension->display_name ?? $name),
            'description' => $manifest->description ?? $extension->description,
            'available_version' => $manifest?->version,
            'manifest' => $manifest?->toArray() ?? ($extension->isInstalled() ? $extension->manifest : null),
            'errors' => $result->isValid() ? null : $result->errors,
        ]);

        if (! $extension->isInstalled()) {
            // A failed install keeps its failure message until it is retried.
            if (! $result->isValid()) {
                $extension->state = ModuleState::Invalid;
            } elseif ($isNew || $extension->state !== ModuleState::Failed) {
                $extension->state = ModuleState::Discovered;
            }
        } elseif (! $result->isValid()) {
            $this->fail($extension, 'The package files on disk are invalid: '.implode(' ', $result->errors));
        } elseif ($manifest !== null && Comparator::greaterThan($manifest->version, (string) $extension->version)) {
            if ($extension->state !== ModuleState::UpdatePending && $extension->state !== ModuleState::Failed) {
                $extension->enabled_before_update = $extension->state === ModuleState::Enabled;
                $extension->state = ModuleState::UpdatePending;
            }
        } elseif ($manifest !== null && Comparator::lessThan($manifest->version, (string) $extension->version)) {
            $this->fail($extension, "The package files (v{$manifest->version}) are older than the installed version (v{$extension->version}). Restore the newer files; downgrades are not supported.");
        } elseif ($extension->state === ModuleState::UpdatePending) {
            // Newer files were removed again: back to how it was.
            $extension->state = $extension->enabled_before_update ? ModuleState::Enabled : ModuleState::Disabled;
        }

        $changed = $isNew || $extension->isDirty('state');
        $extension->save();

        if ($changed) {
            $this->record($extension, $isNew ? 'discovered' : 'state_changed', $from, $result->isValid(), $result->isValid() ? null : implode(' ', $result->errors), $actor);
        }
    }

    private function missing(Extension $extension, ?User $actor): void
    {
        if (! $extension->isInstalled()) {
            $extension->delete();

            return;
        }

        if ($extension->state !== ModuleState::Failed) {
            $from = $extension->state;
            $this->fail($extension, "The package files are missing from modules/{$extension->path}. Restore them to enable, update or uninstall the module.");
            $extension->save();
            $this->record($extension, 'files_missing', $from, false, $extension->failure, $actor);
        }
    }

    private function fail(Extension $extension, string $reason): void
    {
        if ($extension->state === ModuleState::Enabled) {
            $extension->enabled_before_update = true;
        }

        $extension->forceFill(['state' => ModuleState::Failed, 'failure' => $reason, 'failed_at' => now()]);
    }
}
