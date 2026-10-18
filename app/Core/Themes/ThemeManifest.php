<?php

namespace App\Core\Themes;

/**
 * A validated `theme.json`. Pure data; a theme contains no executable code.
 */
final class ThemeManifest
{
    public const FILE = 'theme.json';

    public const MAX_BYTES = 131072;

    /**
     * @param  array<string, string>  $requires  module name => constraint
     * @param  list<string>  $capabilities
     * @param  array<string, mixed>  $design  flat design defaults (ThemeSchema::designFields keys)
     * @param  list<array{family: string, file: string, weight: string, style: string}>  $fonts
     * @param  array<string, string>  $darkColors
     * @param  array<string, list<array{type: string, settings: array<string, mixed>}>>  $sections
     * @param  list<array{version: string, date: string, notes: string}>  $changelog
     * @param  array<string, mixed>  $customizer
     * @param  array<string, string>  $frontend
     */
    public function __construct(
        public readonly string $name,
        public readonly string $displayName,
        public readonly ?string $description,
        public readonly string $version,
        public readonly string $platformVersion,
        public readonly ?string $author,
        public readonly ?string $license,
        public readonly array $requires,
        public readonly array $capabilities,
        public readonly ?string $assets,
        public readonly ?string $screenshot,
        public readonly array $design,
        public readonly array $fonts,
        public readonly array $darkColors,
        public readonly array $sections,
        public readonly ?string $demoContent,
        public readonly array $changelog,
        public readonly array $customizer = [],
        public readonly array $frontend = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  a manifest that already passed validation
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            displayName: (string) $data['display_name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            version: (string) $data['version'],
            platformVersion: (string) $data['platform_version'],
            author: isset($data['author']) ? (string) $data['author'] : null,
            license: isset($data['license']) ? (string) $data['license'] : null,
            requires: (array) ($data['requires'] ?? []),
            capabilities: array_values((array) ($data['capabilities'] ?? [])),
            assets: isset($data['assets']) ? (string) $data['assets'] : null,
            screenshot: isset($data['screenshot']) ? (string) $data['screenshot'] : null,
            design: (array) ($data['design'] ?? []),
            fonts: array_values(array_map(fn (array $font) => [
                'family' => (string) $font['family'],
                'file' => (string) $font['file'],
                'weight' => (string) ($font['weight'] ?? '400'),
                'style' => (string) ($font['style'] ?? 'normal'),
            ], (array) ($data['fonts'] ?? []))),
            darkColors: (array) ($data['dark_colors'] ?? []),
            sections: (array) ($data['sections'] ?? []),
            demoContent: isset($data['demo_content']) ? (string) $data['demo_content'] : null,
            changelog: array_values((array) ($data['changelog'] ?? [])),
            customizer: (array) ($data['customizer'] ?? []),
            frontend: (array) ($data['frontend'] ?? []),
        );
    }

    public function vendor(): string
    {
        return explode('/', $this->name)[0];
    }

    public function package(): string
    {
        return explode('/', $this->name)[1];
    }

    /**
     * Settings key namespace for merchant customizations.
     */
    public function settingsPrefix(): string
    {
        return "theme.{$this->vendor()}.{$this->package()}";
    }

    /**
     * The theme's design defaults over the built-in ones.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return array_replace(ThemeSchema::builtinDesign(), $this->design, array_map(fn (array $field) => $field['default'], $this->customFields()));
    }

    /** @return array<string, array<string, mixed>> */
    public function customFields(): array
    {
        return (array) ($this->customizer['fields'] ?? []);
    }

    /**
     * Named palettes a theme ships: applying one sets several design fields
     * at once, then individual fields remain editable.
     *
     * @return array<string, array<string, mixed>>
     */
    public function presets(): array
    {
        return (array) ($this->customizer['presets'] ?? []);
    }

    /** @return array<string, array<string, mixed>> */
    public function designFields(): array
    {
        return ThemeSchema::designFields() + $this->customFields();
    }

    /**
     * @return list<string>
     */
    public function customFontFamilies(): array
    {
        return array_map(fn (array $font) => $font['family'], $this->fonts);
    }

    /**
     * @return list<array{type: string, settings: array<string, mixed>}>
     */
    public function defaultSections(string $page): array
    {
        return $this->sections[$page] ?? ($page === 'home' ? ThemeSchema::builtinHomeSections() : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'version' => $this->version,
            'type' => 'theme',
            'theme_type' => 'storefront',
            'platform_version' => $this->platformVersion,
            'author' => $this->author,
            'license' => $this->license,
            'requires' => $this->requires,
            'capabilities' => $this->capabilities,
            'assets' => $this->assets,
            'screenshot' => $this->screenshot,
            'design' => $this->design,
            'fonts' => $this->fonts,
            'dark_colors' => $this->darkColors,
            'sections' => $this->sections,
            'demo_content' => $this->demoContent,
            'changelog' => $this->changelog,
            'customizer' => $this->customizer,
            'frontend' => $this->frontend,
        ], fn ($value) => $value !== null);
    }
}
