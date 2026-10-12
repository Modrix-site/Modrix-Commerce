<?php

namespace App\Core\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\Models\ExtensionEvent;
use App\Core\Extensions\ModuleState;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Discovers theme packages under the themes directory and keeps their
 * `extensions` rows (type "theme") in step with them.
 *
 * Theme states: discovered (available), invalid, enabled (the active theme),
 * failed (the active theme's files are missing). The active theme keeps
 * rendering its applied snapshot while newer or broken files sit on disk;
 * the merchant applies updates explicitly.
 */
final class ThemeRepository
{
    public function __construct(private readonly ThemeManifestValidator $validator) {}

    public static function path(): string
    {
        return rtrim((string) config('ecomodular.themes.path', base_path('themes')), '/\\');
    }

    public static function packageDir(string $name): string
    {
        return self::path().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
    }

    /**
     * @return Collection<int, Extension>
     */
    public function all(): Collection
    {
        return Extension::query()->where('type', 'theme')->orderBy('name')->get();
    }

    public function find(string $name): ?Extension
    {
        return Extension::query()->where('type', 'theme')->where('name', $name)->first();
    }

    public function active(): ?Extension
    {
        return Extension::query()->where('type', 'theme')->where('state', ModuleState::Enabled)->first();
    }

    public function sync(?User $actor = null): void
    {
        $scanned = [];

        foreach (glob(self::path().'/*/*/'.ThemeManifest::FILE) ?: [] as $file) {
            $dir = dirname($file);
            $scanned[basename(dirname($dir)).'/'.basename($dir)] = $dir;
        }

        ksort($scanned);

        DB::transaction(function () use ($scanned, $actor) {
            foreach ($scanned as $name => $dir) {
                $this->apply($name, $dir, $actor);
            }

            foreach ($this->all() as $theme) {
                if (! isset($scanned[$theme->name])) {
                    $this->missing($theme, $actor);
                }
            }
        });
    }

    /**
     * @return array{0: ThemeManifest|null, 1: list<string>}
     */
    public function validate(string $name, string $dir): array
    {
        $file = $dir.DIRECTORY_SEPARATOR.ThemeManifest::FILE;

        if (filesize($file) > ThemeManifest::MAX_BYTES) {
            return [null, ['theme.json is larger than 128 KB.']];
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return [null, ["theme.json is not valid JSON: {$e->getMessage()}."]];
        }

        return $this->validator->validate($dir, $name, $data);
    }

    public function record(Extension $theme, string $action, ?ModuleState $from, bool $success = true, ?string $message = null, ?User $actor = null): void
    {
        ExtensionEvent::query()->forceCreate([
            'extension' => "theme:{$theme->name}",
            'action' => $action,
            'from_state' => $from?->value,
            'to_state' => $theme->state->value,
            'version' => $theme->version ?? $theme->available_version,
            'success' => $success,
            'message' => $message === null ? null : mb_substr($message, 0, 2000),
            'actor_id' => $actor?->id,
        ]);
    }

    private function apply(string $name, string $dir, ?User $actor): void
    {
        [$manifest, $errors] = $this->validate($name, $dir);
        $theme = $this->find($name) ?? new Extension;
        $isNew = ! $theme->exists;
        $from = $isNew ? null : $theme->state;

        $theme->forceFill([
            'name' => $name,
            'type' => 'theme',
            'path' => $name,
            'display_name' => $manifest->displayName ?? ($theme->display_name ?? $name),
            'description' => $manifest->description ?? $theme->description,
            'available_version' => $manifest?->version,
            'manifest' => $manifest?->toArray() ?? $theme->manifest,
            'errors' => $manifest === null ? $errors : null,
        ]);

        $wasActive = $theme->state === ModuleState::Enabled
            || ($theme->state === ModuleState::Failed && $theme->enabled_before_update);

        if ($wasActive && $theme->applied_manifest !== null) {
            // The active theme keeps rendering its applied snapshot, even when the new files are invalid.
            $theme->forceFill(['state' => ModuleState::Enabled, 'failure' => null, 'enabled_before_update' => false]);
        } elseif ($manifest === null) {
            $theme->state = ModuleState::Invalid;
        } else {
            $theme->state = ModuleState::Discovered;
        }

        $changed = $isNew || $theme->isDirty('state');
        $theme->save();

        if ($changed) {
            $this->record($theme, $isNew ? 'discovered' : 'state_changed', $from, $manifest !== null, $manifest === null ? implode(' ', $errors) : null, $actor);
        }
    }

    private function missing(Extension $theme, ?User $actor): void
    {
        if ($theme->state !== ModuleState::Enabled) {
            if ($theme->state !== ModuleState::Failed) {
                $theme->delete(); // Merchant customizations live in settings and survive.
            }

            return;
        }

        $theme->forceFill([
            'state' => ModuleState::Failed,
            'enabled_before_update' => true,
            'failure' => "The theme files are missing from themes/{$theme->path}. The store uses the default theme until they are restored.",
            'failed_at' => now(),
        ])->save();
        $this->record($theme, 'files_missing', ModuleState::Enabled, false, $theme->failure, $actor);
    }
}
