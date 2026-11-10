<?php

namespace App\Core\Themes;

use App\Core\Localization\ContentTranslations;

/**
 * The theme a storefront request is rendered with: the manifest snapshot,
 * the effective design (defaults + merchant overrides) and where it came from.
 */
final class ResolvedTheme
{
    public const SOURCE_ACTIVE = 'active';

    public const SOURCE_PREVIEW = 'preview';

    public const SOURCE_DEFAULT = 'default';

    public const SOURCE_BUILTIN = 'builtin';

    /**
     * @param  array<string, mixed>  $design
     */
    public function __construct(
        public readonly ThemeManifest $manifest,
        public readonly string $source,
        public readonly array $design,
        private readonly ThemeCustomizations $customizations,
        private readonly bool $hasPackage,
    ) {}

    /**
     * @return list<array{type: string, settings: array<string, mixed>}>
     */
    public function sections(string $page): array
    {
        return $this->hasPackage
            ? $this->customizations->sections($this->manifest, $page)
            : $this->manifest->defaultSections($page);
    }

    /**
     * URL of an image value ("asset:…" or "media:…"), or null.
     */
    public function imageUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_starts_with($value, 'media:')) {
            return $this->customizations->mediaUrl($value);
        }

        if (str_starts_with($value, 'asset:') && $this->hasPackage) {
            return $this->assetUrl(substr($value, strlen('asset:')));
        }

        return null;
    }

    public function assetUrl(string $path): string
    {
        return route('theme.asset', [
            'vendor' => $this->manifest->vendor(),
            'theme' => $this->manifest->package(),
            'path' => $path,
            'v' => $this->manifest->version,
        ]);
    }

    public function css(): string
    {
        return ThemeCss::build($this);
    }

    /**
     * The `theme` page prop shared with storefront pages.
     *
     * @return array<string, mixed>
     */
    public function toProps(): array
    {
        $translations = app(ContentTranslations::class);
        $pages = PageSettingFields::resolve($this->design);
        $warm = [];
        foreach (PageSettingFields::fields() as $key => $field) {
            if (! in_array($field['type'], ['string', 'text'], true) || $translations->skipKey($key)) {
                continue;
            }
            [$page, $name] = explode('.', $key, 2);
            if (is_string($pages[$page][$name] ?? null)) {
                $warm[] = $pages[$page][$name];
            }
        }
        foreach ($this->manifest->customFields() as $key => $field) {
            if (! in_array($field['type'], ['string', 'text', 'textarea'], true) || $translations->skipKey($key)) {
                continue;
            }
            $value = $this->design[$key] ?? $field['default'];
            if (is_string($value)) {
                $warm[] = $value;
            }
        }
        $warm[] = $this->design['announcement'] ?? '';
        $warm[] = $this->design['footer_text'] ?? '';
        foreach ($this->design as $key => $value) {
            if (is_string($key) && is_string($value) && ! $translations->skipKey($key)
                && (str_starts_with($key, 'menu_') || str_starts_with($key, 'footer_'))) {
                $warm[] = $value;
            }
        }
        $translations->warm($warm);

        foreach (PageSettingFields::fields() as $key => $field) {
            if (! in_array($field['type'], ['string', 'text'], true) || $translations->skipKey($key)) {
                continue;
            }
            [$page, $name] = explode('.', $key, 2);
            if (is_string($pages[$page][$name] ?? null)) {
                $pages[$page][$name] = $translations->translate($pages[$page][$name]);
            }
        }

        return [
            'name' => $this->manifest->name,
            'displayName' => $this->manifest->displayName,
            'version' => $this->manifest->version,
            'source' => $this->source,
            'frontend' => $this->manifest->frontend,
            'settings' => collect($this->manifest->customFields())->mapWithKeys(function (array $field, string $key) use ($translations) {
                $name = substr($key, 7);
                if ($field['type'] === 'image') {
                    return [$name => $this->imageUrl($this->design[$key] ?? null)];
                }
                $value = $this->design[$key] ?? $field['default'];
                if (in_array($field['type'], ['string', 'text', 'textarea'], true) && is_string($value) && ! $translations->skipKey($key)) {
                    $value = $translations->translate($value);
                }

                return [$name => $value];
            })->all(),
            'css' => $this->css(),
            'pages' => $pages,
            'layout' => [
                'header' => $this->design['layout.header'],
                'footer' => $this->design['layout.footer'],
                'productCard' => $this->design['layout.product_card'],
                'gridColumns' => (int) $this->design['layout.grid_columns'],
                'productPage' => $this->design['layout.product_page'],
                'density' => $this->design['density'],
                'container' => $this->design['container'],
            ],
            'logo' => $this->imageUrl($this->design['logo']),
            'favicon' => $this->imageUrl($this->design['favicon']),
            'announcement' => $this->design['announcement'] !== '' ? $translations->translate($this->design['announcement']) : null,
            'footerText' => $this->design['footer_text'] !== '' ? $translations->translate($this->design['footer_text']) : null,
            'content' => app(StorefrontLinks::class)->content(collect(array_intersect_key($this->design, array_filter(ThemeSchema::designFields(), fn (array $field, string $key) => str_starts_with($key, 'menu_') || str_starts_with($key, 'footer_') || str_starts_with($key, 'contact_') || $key === 'help_link', ARRAY_FILTER_USE_BOTH)))
                ->map(fn (mixed $value, string $key) => is_string($value) && ! str_ends_with($key, '_link') && ! $translations->skipKey($key)
                    ? $translations->translate($value)
                    : $value)
                ->all()),
        ];
    }
}
