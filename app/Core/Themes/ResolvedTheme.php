<?php

namespace App\Core\Themes;

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
        return [
            'name' => $this->manifest->name,
            'displayName' => $this->manifest->displayName,
            'version' => $this->manifest->version,
            'source' => $this->source,
            'css' => $this->css(),
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
            'announcement' => $this->design['announcement'] !== '' ? $this->design['announcement'] : null,
            'footerText' => $this->design['footer_text'] !== '' ? $this->design['footer_text'] : null,
        ];
    }
}
