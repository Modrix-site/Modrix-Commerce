<?php

namespace App\Core\Themes\Sections;

use App\Core\Settings\SettingsRepository;
use App\Core\Themes\ThemeCustomizations;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use App\Core\Themes\ThemeSchema;
use Illuminate\Support\Facades\Cache;

/**
 * Templates and section groups of a sections v2 theme: the package defaults,
 * the merchant's saved edits (setting "<prefix>.templates") and, for a staff
 * member in the theme editor, their unsaved draft (cache, per user).
 *
 * Keys are "template:<name>" or "group:<name>".
 */
final class ThemeTemplates
{
    private const DRAFT_TTL_MINUTES = 120;

    /** @var array<string, array<string, mixed>> */
    private array $packages = [];

    /** Staff member whose editor drafts apply to this request (theme editor preview). */
    private ?int $draftUser = null;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ThemeCustomizations $customizations,
    ) {}

    public static function supports(ThemeManifest $manifest): bool
    {
        return in_array('sections', $manifest->capabilities, true);
    }

    /**
     * @return list<string> every editable key, header group first
     */
    public static function keys(): array
    {
        return [
            ...array_map(fn (string $g) => "group:{$g}", SectionPackage::GROUPS),
            ...array_map(fn (string $t) => "template:{$t}", SectionPackage::TEMPLATES),
        ];
    }

    /**
     * @return array{0: string, 1: string}|null [kind, name]
     */
    public static function parse(string $key): ?array
    {
        [$kind, $name] = array_pad(explode(':', $key, 2), 2, '');

        return match (true) {
            $kind === 'template' && in_array($name, SectionPackage::TEMPLATES, true),
            $kind === 'group' && in_array($name, SectionPackage::GROUPS, true) => [$kind, $name],
            default => null,
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function schemas(ThemeManifest $manifest): array
    {
        return $this->package($manifest)['schemas'];
    }

    /**
     * The template or group to render: the editor draft, the saved edit or the package default.
     *
     * @return array{sections: array<string, mixed>, order: list<string>}
     */
    public function get(ThemeManifest $manifest, string $key): array
    {
        $draft = $this->draftUser !== null ? ($this->draft($manifest, $this->draftUser)[$key] ?? null) : null;

        return $draft ?? $this->saved($manifest, $key);
    }

    /**
     * @return array{sections: array<string, mixed>, order: list<string>}
     */
    public function saved(ThemeManifest $manifest, string $key): array
    {
        $stored = ((array) ($this->settings->get("{$manifest->settingsPrefix()}.templates") ?? []))[$key] ?? null;

        if (is_array($stored)) {
            // Re-checked on read: a theme update may have removed a section type or setting.
            return $this->check($manifest, $key, $stored)[0];
        }

        return $this->default($manifest, $key);
    }

    /**
     * @return array{sections: array<string, mixed>, order: list<string>}
     */
    public function default(ThemeManifest $manifest, string $key): array
    {
        [$kind, $name] = self::parse($key) ?? ['template', 'index'];
        $package = $this->package($manifest);
        $data = $kind === 'group' ? ($package['groups'][$name] ?? null) : ($package['templates'][$name] ?? null);

        return is_array($data) ? $this->check($manifest, $key, $data)[0] : ['sections' => [], 'order' => []];
    }

    public function isCustomized(ThemeManifest $manifest, string $key): bool
    {
        return array_key_exists($key, (array) ($this->settings->get("{$manifest->settingsPrefix()}.templates") ?? []));
    }

    /**
     * @return array{0: array{sections: array<string, mixed>, order: list<string>}, 1: array<string, string>}
     */
    public function check(ThemeManifest $manifest, string $key, mixed $data): array
    {
        [$kind, $name] = self::parse($key) ?? [null, null];

        if ($kind === null) {
            return [['sections' => [], 'order' => []], ['' => 'is not a template or section group']];
        }

        return SectionPackage::check($this->schemas($manifest), $kind, $name, $data, $this->customizations->assetChecker($manifest));
    }

    /**
     * Validates and stores several templates/groups at once; nothing is stored when any fails.
     *
     * @param  array<string, mixed>  $templates  key => template
     * @return array<string, array<string, string>> errors per key (empty on success)
     */
    public function save(ThemeManifest $manifest, array $templates): array
    {
        $clean = [];
        $errors = [];

        foreach ($templates as $key => $data) {
            [$value, $problems] = $this->check($manifest, (string) $key, $data);

            if ($problems !== []) {
                $errors[$key] = $problems;
            } else {
                $clean[$key] = $value;
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $prefix = "{$manifest->settingsPrefix()}.templates";
        $this->settings->set($prefix, array_replace((array) ($this->settings->get($prefix) ?? []), $clean));

        return [];
    }

    public function reset(ThemeManifest $manifest): void
    {
        $this->settings->forget(["{$manifest->settingsPrefix()}.templates"]);
    }

    /**
     * Render this request with the user's editor drafts (any theme they are editing),
     * or with saved templates only (null). Set on every storefront request so
     * nothing carries over between requests in long-running workers.
     */
    public function useDraft(?int $userId): void
    {
        $this->draftUser = $userId;
    }

    public function usingDraft(): bool
    {
        return $this->draftUser !== null;
    }

    /**
     * Unsaved theme-settings changes from the editor, validated, for this request.
     *
     * @return array<string, mixed>
     */
    public function designDraft(ThemeManifest $manifest): array
    {
        if ($this->draftUser === null) {
            return [];
        }

        $fields = $manifest->designFields();
        $clean = [];

        foreach ((array) ($this->draft($manifest, $this->draftUser)['design'] ?? []) as $key => $value) {
            if (isset($fields[$key]) && $value !== null && $value !== '') {
                [$valid, $error] = ThemeSchema::check($fields[$key], $value, $this->customizations->assetChecker($manifest), $manifest->customFontFamilies());

                if ($error === null) {
                    $clean[$key] = $valid;
                }
            }
        }

        return $clean;
    }

    /**
     * @return array<string, array{sections: array<string, mixed>, order: list<string>}>
     */
    public function draft(ThemeManifest $manifest, int $userId): array
    {
        return (array) Cache::get($this->draftKey($manifest, $userId), []);
    }

    /**
     * Stores unsaved editor changes (validated) for live preview. Invalid
     * parts are dropped from the preview, never stored on the theme.
     *
     * @param  array<string, mixed>  $templates
     * @param  array<string, mixed>|null  $design  theme settings changes (validated when rendered)
     * @return array<string, array<string, string>> errors per key
     */
    public function putDraft(ThemeManifest $manifest, int $userId, array $templates, ?array $design = null): array
    {
        $draft = $this->draft($manifest, $userId);
        $errors = [];

        if ($design !== null) {
            $draft['design'] = array_intersect_key($design, $manifest->designFields());
        }

        foreach ($templates as $key => $data) {
            if (self::parse((string) $key) === null) {
                continue;
            }

            [$value, $problems] = $this->check($manifest, (string) $key, $data);
            $draft[$key] = $value;

            if ($problems !== []) {
                $errors[$key] = $problems;
            }
        }

        Cache::put($this->draftKey($manifest, $userId), $draft, now()->addMinutes(self::DRAFT_TTL_MINUTES));

        return $errors;
    }

    public function forgetDraft(ThemeManifest $manifest, int $userId): void
    {
        Cache::forget($this->draftKey($manifest, $userId));
    }

    private function draftKey(ThemeManifest $manifest, int $userId): string
    {
        return "theme-editor-draft:{$manifest->name}:{$userId}";
    }

    /**
     * @return array{schemas: array<string, array<string, mixed>>, templates: array<string, mixed>, groups: array<string, mixed>}
     */
    private function package(ThemeManifest $manifest): array
    {
        return $this->packages[$manifest->name] ??= Cache::remember(
            'theme-sections:'.$manifest->name.':'.$manifest->version.':'.$this->fingerprint($manifest),
            600,
            fn () => array_intersect_key(SectionPackage::read(ThemeRepository::packageDir($manifest->name)), array_flip(['schemas', 'templates', 'groups'])),
        );
    }

    /** Changes when any section, template or group file changes (theme development without version bumps). */
    private function fingerprint(ThemeManifest $manifest): string
    {
        $dir = ThemeRepository::packageDir($manifest->name);
        $files = [...(glob($dir.'/sections/*.json') ?: []), ...(glob($dir.'/templates/*.json') ?: []), ...(glob($dir.'/groups/*.json') ?: [])];

        return md5(implode('|', array_map(fn (string $f) => $f.':'.filemtime($f), $files)));
    }
}
