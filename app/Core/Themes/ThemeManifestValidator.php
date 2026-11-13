<?php

namespace App\Core\Themes;

use App\Core\Extensions\ManifestValidator;
use App\Core\Platform;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Validates a theme package: the manifest (docs/extensions/theme.schema.json)
 * and package files. Explicit frontend entries permit compiled TypeScript
 * and CSS under src/. Server code and markup are prohibited, and every
 * referenced path must stay inside the package.
 */
final class ThemeManifestValidator
{
    private const KEYS = [
        '$schema', 'name', 'display_name', 'description', 'version', 'type', 'theme_type', 'platform_version',
        'author', 'license', 'requires', 'capabilities', 'assets', 'screenshot', 'design', 'fonts', 'dark_colors',
        'sections', 'demo_content', 'changelog', 'customizer', 'customizer_file', 'frontend',
    ];

    /** File types a theme package may contain. Files without an extension are allowed for LICENSE/README. */
    private const PACKAGE_EXTENSIONS = ['json', 'md', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'woff2', ''];

    private const MAX_FILES = 2000;

    private const MAX_ASSET_BYTES = 5_242_880;

    /** @var list<string> */
    private array $errors = [];

    /**
     * @return array{0: ThemeManifest|null, 1: list<string>}
     */
    public function validate(string $packageDir, string $expectedName, mixed $data): array
    {
        $this->errors = [];

        if (! is_array($data) || array_is_list($data)) {
            return [null, ['The manifest must be a JSON object.']];
        }

        foreach (array_diff(array_keys($data), self::KEYS) as $unknown) {
            $this->errors[] = "Unknown manifest key \"{$unknown}\".";
        }

        $frontend = $data['frontend'] ?? [];
        $this->errors = [...$this->errors, ...ThemeFrontend::errors($packageDir, $frontend)];
        if (! is_array($frontend)) {
            $frontend = [];
        }
        // Section components (capability "sections") are browser source under src/ too.
        $sectionsCapability = is_array($data['capabilities'] ?? null) && in_array('sections', $data['capabilities'], true);
        $this->packageFiles($packageDir, $frontend !== [] || $sectionsCapability);

        $name = $this->string($data, 'name', 100, true);
        if ($name !== null && ! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,48}[a-z0-9])?\/[a-z0-9](?:[a-z0-9-]{0,48}[a-z0-9])?$/', $name)) {
            $this->errors[] = 'name must look like "vendor/theme".';
        } elseif ($name !== null && $name !== $expectedName) {
            $this->errors[] = "name \"{$name}\" does not match the package directory \"{$expectedName}\".";
        }

        $this->string($data, 'display_name', 120, true);
        $this->string($data, 'description', 500);
        $this->string($data, 'author', 120);
        $this->string($data, 'license', 60);

        $version = $this->string($data, 'version', 64, true);
        if ($version !== null && ! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
            $this->errors[] = 'version must be a semantic version such as 1.2.0.';
        }

        if (($data['type'] ?? null) !== 'theme') {
            $this->errors[] = 'type must be "theme".';
        }

        if (($data['theme_type'] ?? null) !== 'storefront') {
            $this->errors[] = 'theme_type must be "storefront".';
        }

        $this->platform($data);
        $this->requires($data);
        $capabilities = $this->capabilities($data);

        $assetsDir = null;
        if (isset($data['assets'])) {
            $assetsDir = is_string($data['assets']) ? ManifestValidator::resolvePath($packageDir, $data['assets'], directory: true) : null;
            if ($assetsDir === null) {
                $this->errors[] = 'assets must be an existing directory inside the package.';
            }
        }

        $assetExists = fn (string $path): bool => $this->assetExists($packageDir, $assetsDir, $path, ThemeSchema::IMAGE_EXTENSIONS);

        if (isset($data['screenshot']) && (! is_string($data['screenshot']) || ! $assetExists($data['screenshot']))) {
            $this->errors[] = 'screenshot must be a PNG, JPEG or WebP file in the assets directory.';
        }

        if (isset($data['customizer_file'])) {
            $settingsFile = is_string($data['customizer_file']) ? ManifestValidator::resolvePath($packageDir, $data['customizer_file'], directory: false) : null;
            if ($settingsFile === null || strtolower(pathinfo($settingsFile, PATHINFO_EXTENSION)) !== 'json' || filesize($settingsFile) > ThemeManifest::MAX_BYTES) {
                $this->errors[] = 'customizer_file must be a JSON file inside the theme, up to 128 KB.';
            } else {
                $data['customizer'] = json_decode((string) file_get_contents($settingsFile), true, 32);
                if (! is_array($data['customizer'])) {
                    $this->errors[] = 'customizer_file must contain a valid JSON object.';
                }
            }
        }
        $fonts = $this->fonts($data, $capabilities, $packageDir, $assetsDir);
        if (isset($data['customizer'])) {
            $this->errors = [...$this->errors, ...ThemeCustomizerSchema::errors($data['customizer'], $assetExists)];
        }
        $this->design($data, $assetExists, $fonts);
        $this->darkColors($data, $capabilities);
        $this->sections($data, $capabilities, $assetExists);
        if (in_array('sections', $capabilities, true)) {
            $this->errors = [...$this->errors, ...Sections\SectionPackage::errors($packageDir, $assetExists)];
        }
        $this->demoContent($data, $capabilities, $packageDir);
        $this->changelog($data);

        return $this->errors === [] ? [ThemeManifest::fromArray($data), []] : [null, $this->errors];
    }

    /**
     * @param  list<string>  $extensions
     */
    private function assetExists(string $packageDir, ?string $assetsDir, string $path, array $extensions): bool
    {
        if ($assetsDir === null) {
            return false;
        }

        $resolved = ManifestValidator::resolvePath($packageDir, $path, directory: false);

        return $resolved !== null
            && str_starts_with($resolved, $assetsDir.DIRECTORY_SEPARATOR)
            && in_array(strtolower(pathinfo($resolved, PATHINFO_EXTENSION)), $extensions, true)
            && filesize($resolved) <= self::MAX_ASSET_BYTES;
    }

    private function packageFiles(string $packageDir, bool $frontend = false): void
    {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packageDir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (++$count > self::MAX_FILES) {
                $this->errors[] = 'The package contains too many files.';

                return;
            }

            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($packageDir))), '/');

            if ($file->isLink()) {
                $this->errors[] = "Symbolic links are not allowed ({$relative}).";
            } elseif (! in_array(strtolower($file->getExtension()), self::PACKAGE_EXTENSIONS, true)
                && ! ($frontend && str_starts_with($relative, 'src/') && in_array(strtolower($file->getExtension()), ['tsx', 'ts', 'css'], true))
                // Prebuilt screens (ecomodular:module:build), served only by /theme-build.
                && ! ($frontend && str_starts_with($relative, 'dist/') && in_array(strtolower($file->getExtension()), ['js', 'css', 'json'], true))) {
                $this->errors[] = "Themes may only contain data files, declared browser source under src/ and its prebuilt dist/; {$relative} is not allowed.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function platform(array $data): void
    {
        $constraint = $this->string($data, 'platform_version', 100, true);

        if ($constraint === null) {
            return;
        }

        try {
            (new VersionParser)->parseConstraints($constraint);
        } catch (Throwable) {
            $this->errors[] = 'platform_version is not a valid version constraint.';

            return;
        }

        if (! Semver::satisfies(Platform::VERSION, $constraint)) {
            $this->errors[] = "Requires platform version {$constraint}; this store runs ".Platform::VERSION.'.';
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requires(array $data): void
    {
        $requires = $data['requires'] ?? [];

        if (! is_array($requires) || ($requires !== [] && array_is_list($requires))) {
            $this->errors[] = 'requires must be an object of "vendor/module": "constraint".';

            return;
        }

        foreach ($requires as $module => $constraint) {
            try {
                if (! preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', (string) $module) || ! is_string($constraint)) {
                    throw new \InvalidArgumentException;
                }
                (new VersionParser)->parseConstraints($constraint);
            } catch (Throwable) {
                $this->errors[] = "requires: {$module} is not a valid module requirement.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function capabilities(array $data): array
    {
        $capabilities = $data['capabilities'] ?? [];

        if (! is_array($capabilities) || ! array_is_list($capabilities)) {
            $this->errors[] = 'capabilities must be a list.';

            return [];
        }

        foreach ($capabilities as $capability) {
            if (! is_string($capability) || ! array_key_exists($capability, ThemeSchema::CAPABILITIES)) {
                $this->errors[] = 'Unknown capability "'.(is_string($capability) ? $capability : gettype($capability)).'".';
            }
        }

        return array_values(array_filter($capabilities, 'is_string'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $capabilities
     * @return list<string> declared custom font families
     */
    private function fonts(array $data, array $capabilities, string $packageDir, ?string $assetsDir): array
    {
        $fonts = $data['fonts'] ?? [];

        if (! is_array($fonts) || ! array_is_list($fonts) || count($fonts) > 6) {
            $this->errors[] = 'fonts must be a list of at most 6 fonts.';

            return [];
        }

        if ($fonts !== [] && ! in_array('custom_fonts', $capabilities, true)) {
            $this->errors[] = 'Shipping fonts requires the "custom_fonts" capability.';
        }

        $families = [];

        foreach ($fonts as $i => $font) {
            if (! is_array($font)
                || ! is_string($font['family'] ?? null) || ! preg_match('/^[A-Za-z][A-Za-z0-9 ]{0,39}$/', $font['family'])
                || ! is_string($font['file'] ?? null) || ! $this->assetExists($packageDir, $assetsDir, $font['file'], ['woff2'])
                || ! is_string($font['weight'] ?? '400') || ! preg_match('/^\d{3}( \d{3})?$/', (string) ($font['weight'] ?? '400'))
                || ! in_array($font['style'] ?? 'normal', ['normal', 'italic'], true)) {
                $this->errors[] = "fonts[{$i}] needs a family (letters, digits, spaces), a .woff2 file in the assets directory, a weight like \"400\" or \"300 700\" and a style.";

                continue;
            }

            $families[] = $font['family'];
        }

        return $families;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(string): bool  $assetExists
     * @param  list<string>  $fonts
     */
    private function design(array $data, callable $assetExists, array $fonts): void
    {
        $design = $data['design'] ?? [];

        if (! is_array($design) || ($design !== [] && array_is_list($design))) {
            $this->errors[] = 'design must be an object of design settings.';

            return;
        }

        $fields = ThemeSchema::designFields();

        foreach ($design as $key => $value) {
            if (! isset($fields[$key])) {
                $this->errors[] = "design: unknown setting \"{$key}\".";

                continue;
            }

            [, $error] = ThemeSchema::check($fields[$key], $value, $assetExists, $fonts);

            if ($error !== null) {
                $this->errors[] = "design.{$key} {$error}.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $capabilities
     */
    private function darkColors(array $data, array $capabilities): void
    {
        if (! isset($data['dark_colors'])) {
            return;
        }

        if (! in_array('dark_mode', $capabilities, true)) {
            $this->errors[] = 'dark_colors requires the "dark_mode" capability.';
        }

        if (! is_array($data['dark_colors'])) {
            $this->errors[] = 'dark_colors must be an object.';

            return;
        }

        foreach ($data['dark_colors'] as $key => $value) {
            if (! array_key_exists((string) $key, ThemeSchema::COLORS) || ! is_string($value) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $this->errors[] = "dark_colors.{$key} must be a known color set to #rrggbb.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $capabilities
     * @param  callable(string): bool  $assetExists
     */
    private function sections(array $data, array $capabilities, callable $assetExists): void
    {
        if (! isset($data['sections'])) {
            return;
        }

        if (! is_array($data['sections']) || array_is_list($data['sections'])) {
            $this->errors[] = 'sections must be an object of page => list of sections.';

            return;
        }

        if (! in_array('home.sections', $capabilities, true)) {
            $this->errors[] = 'Declaring sections requires the "home.sections" capability.';
        }

        foreach ($data['sections'] as $page => $sections) {
            if (! in_array($page, ThemeSchema::SECTION_PAGES, true)) {
                $this->errors[] = "sections: unknown page \"{$page}\".";

                continue;
            }

            [, $errors] = ThemeSchema::checkSections($sections, $assetExists);

            foreach ($errors as $where => $error) {
                $this->errors[] = "sections.{$page}.{$where}: {$error}.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $capabilities
     */
    private function demoContent(array $data, array $capabilities, string $packageDir): void
    {
        if (! isset($data['demo_content'])) {
            return;
        }

        if (! in_array('demo_content', $capabilities, true)) {
            $this->errors[] = 'demo_content requires the "demo_content" capability.';
        }

        $file = is_string($data['demo_content']) ? ManifestValidator::resolvePath($packageDir, $data['demo_content'], directory: false) : null;

        if ($file === null || ! str_ends_with(strtolower($file), '.json')) {
            $this->errors[] = 'demo_content must be a .json file inside the package.';
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function changelog(array $data): void
    {
        $changelog = $data['changelog'] ?? [];

        if (! is_array($changelog) || ! array_is_list($changelog) || count($changelog) > 50) {
            $this->errors[] = 'changelog must be a list of at most 50 entries.';

            return;
        }

        foreach ($changelog as $i => $entry) {
            if (! is_array($entry)
                || ! is_string($entry['version'] ?? null)
                || ! is_string($entry['date'] ?? null) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry['date'])
                || ! is_string($entry['notes'] ?? null) || mb_strlen($entry['notes']) > 500) {
                $this->errors[] = "changelog[{$i}] needs a version, a date (YYYY-MM-DD) and notes (max 500).";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function string(array $data, string $key, int $max, bool $required = false): ?string
    {
        if (! array_key_exists($key, $data)) {
            if ($required) {
                $this->errors[] = "{$key} is required.";
            }

            return null;
        }

        if (! is_string($data[$key]) || ($required && trim($data[$key]) === '') || mb_strlen($data[$key]) > $max) {
            $this->errors[] = "{$key} must be text of at most {$max} characters.";

            return null;
        }

        return $data[$key];
    }
}
