<?php

namespace App\Core\Themes;

use App\Core\Extensions\ManifestValidator;
use App\Core\Media\ImageSanitizer;
use App\Core\Settings\SettingsRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Merchant overrides of a theme's defaults, stored apart from the theme
 * package (settings "theme.<vendor>.<name>.design" and ".sections") so that
 * theme updates never overwrite them. Overrides that a newer theme version
 * no longer accepts are ignored and reported, never silently deleted.
 */
final class ThemeCustomizations
{
    public const UPLOADS_KEY = 'theme.uploads';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ImageSanitizer $images,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function designOverrides(ThemeManifest $manifest): array
    {
        return (array) ($this->settings->get("{$manifest->settingsPrefix()}.design") ?? []);
    }

    /**
     * Theme defaults with valid merchant overrides applied.
     *
     * @return array{0: array<string, mixed>, 1: list<string>} effective design and the override keys that no longer apply
     */
    public function effectiveDesign(ThemeManifest $manifest): array
    {
        $design = $manifest->defaults();
        $fields = ThemeSchema::designFields();
        $ignored = [];

        foreach ($this->designOverrides($manifest) as $key => $value) {
            [$clean, $error] = isset($fields[$key])
                ? ThemeSchema::check($fields[$key], $value, $this->assetChecker($manifest), $manifest->customFontFamilies())
                : [null, 'unknown'];

            if ($error === null) {
                $design[$key] = $clean;
            } else {
                $ignored[] = (string) $key;
            }
        }

        return [$design, $ignored];
    }

    /**
     * Validates and stores design overrides. An empty value removes the
     * override (back to the theme default).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string> errors keyed by setting
     */
    public function saveDesign(ThemeManifest $manifest, array $input): array
    {
        $fields = ThemeSchema::designFields();
        $overrides = $this->designOverrides($manifest);
        $errors = [];

        foreach ($input as $key => $value) {
            if (! isset($fields[$key])) {
                $errors[$key] = 'is not a design setting';

                continue;
            }

            if ($value === null || $value === '') {
                unset($overrides[$key]);

                continue;
            }

            if ($fields[$key]['type'] === 'image' && is_string($value) && str_starts_with($value, 'media:') && ! $this->isUpload($value)) {
                $errors[$key] = 'is not an uploaded image';

                continue;
            }

            [$clean, $error] = ThemeSchema::check($fields[$key], $value, $this->assetChecker($manifest), $manifest->customFontFamilies());

            if ($error !== null) {
                $errors[$key] = "{$fields[$key]['label']} {$error}.";

                continue;
            }

            $overrides[$key] = $clean;
        }

        if ($errors === []) {
            $this->settings->set("{$manifest->settingsPrefix()}.design", $overrides);
        }

        return $errors;
    }

    /**
     * @return list<array{type: string, settings: array<string, mixed>}>
     */
    public function sections(ThemeManifest $manifest, string $page): array
    {
        $overrides = (array) ($this->settings->get("{$manifest->settingsPrefix()}.sections") ?? []);

        if (! array_key_exists($page, $overrides)) {
            return $manifest->defaultSections($page);
        }

        // Re-validated on read: a theme update may have removed an asset an override pointed at.
        [$sections, $errors] = ThemeSchema::checkSections($overrides[$page], $this->assetChecker($manifest));

        return array_values(array_filter($sections, fn ($section, $i) => ! $this->sectionHasError($errors, $i), ARRAY_FILTER_USE_BOTH));
    }

    public function hasSectionOverrides(ThemeManifest $manifest, string $page): bool
    {
        return array_key_exists($page, (array) ($this->settings->get("{$manifest->settingsPrefix()}.sections") ?? []));
    }

    /**
     * @return array<string, string> errors keyed "index.field"
     */
    public function saveSections(ThemeManifest $manifest, string $page, mixed $sections): array
    {
        if (! in_array($page, ThemeSchema::SECTION_PAGES, true)) {
            return ['page' => 'is not a page with sections'];
        }

        [$clean, $errors] = ThemeSchema::checkSections($sections, $this->assetChecker($manifest));

        foreach ($clean as $i => $section) {
            foreach (ThemeSchema::sectionTypes()[$section['type']]['fields'] as $key => $field) {
                $value = $section['settings'][$key] ?? null;

                if ($field['type'] === 'image' && is_string($value) && str_starts_with($value, 'media:') && ! $this->isUpload($value)) {
                    $errors["{$i}.{$key}"] = "{$field['label']} is not an uploaded image";
                }
            }
        }

        if ($errors === []) {
            $all = (array) ($this->settings->get("{$manifest->settingsPrefix()}.sections") ?? []);
            $all[$page] = $clean;
            $this->settings->set("{$manifest->settingsPrefix()}.sections", $all);
        }

        return $errors;
    }

    /**
     * Removes all merchant overrides of this theme.
     */
    public function reset(ThemeManifest $manifest): void
    {
        $this->settings->forget(["{$manifest->settingsPrefix()}.design", "{$manifest->settingsPrefix()}.sections"]);
    }

    /**
     * Stores a merchant image (re-encoded) and adds it to the image library
     * shared by all themes.
     *
     * @return array{value: string, name: string, url: string}
     */
    public function upload(UploadedFile $file): array
    {
        $clean = $this->images->sanitize($file->getRealPath() ?: '', 'file');
        $path = 'themes/'.Str::random(40).'.'.$clean['extension'];
        Storage::disk($this->disk())->put($path, $clean['bytes'], ['visibility' => 'public']);

        $entry = ['value' => "media:{$path}", 'name' => Str::limit($file->getClientOriginalName(), 80, '')];
        $uploads = $this->uploads();
        $uploads[] = $entry;
        $this->settings->set(self::UPLOADS_KEY, $uploads);

        return [...$entry, 'url' => $this->mediaUrl($entry['value'])];
    }

    /**
     * @return list<array{value: string, name: string}>
     */
    public function uploads(): array
    {
        /** @var list<array{value: string, name: string}> */
        return array_values(array_filter((array) ($this->settings->get(self::UPLOADS_KEY) ?? []), 'is_array'));
    }

    public function mediaUrl(string $value): string
    {
        return Storage::disk($this->disk())->url(substr($value, strlen('media:')));
    }

    /**
     * @return callable(string): bool
     */
    public function assetChecker(ThemeManifest $manifest): callable
    {
        return function (string $path) use ($manifest): bool {
            if ($manifest->assets === null) {
                return false;
            }

            $dir = ThemeRepository::packageDir($manifest->name);
            $assets = ManifestValidator::resolvePath($dir, $manifest->assets, directory: true);
            $file = ManifestValidator::resolvePath($dir, $path, directory: false);

            return $assets !== null && $file !== null && str_starts_with($file, $assets.DIRECTORY_SEPARATOR)
                && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ThemeSchema::IMAGE_EXTENSIONS, true);
        };
    }

    private function isUpload(string $value): bool
    {
        return in_array($value, array_column($this->uploads(), 'value'), true);
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function sectionHasError(array $errors, int $index): bool
    {
        foreach (array_keys($errors) as $key) {
            if (str_starts_with((string) $key, "{$index}.")) {
                return true;
            }
        }

        return false;
    }

    private function disk(): string
    {
        return (string) config('ecomodular.media_disk', 'public');
    }
}
