<?php

namespace App\Core\Extensions;

use App\Core\Platform;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Throwable;

/**
 * Validates a decoded `module.json` against the manifest contract
 * (docs/extensions/module.schema.json) and the package directory.
 *
 * Package files are only inspected (existence, location), never loaded.
 * Cross-package conflicts are checked against the given context.
 */
final class ManifestValidator
{
    private const KEYS = [
        '$schema', 'name', 'display_name', 'description', 'version', 'type', 'platform_version', 'author', 'license',
        'autoload', 'provider', 'requires', 'permissions', 'capabilities', 'migrations', 'routes', 'assets',
        'settings', 'admin_navigation',
    ];

    private const NAME = '/^[a-z0-9](?:[a-z0-9-]{0,48}[a-z0-9])?\/[a-z0-9](?:[a-z0-9-]{0,48}[a-z0-9])?$/';

    private const SEMVER = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/';

    /** Namespaces a module may never claim. */
    private const RESERVED_NAMESPACES = [
        'App\\', 'Database\\', 'Tests\\', 'Illuminate\\', 'Laravel\\', 'Symfony\\', 'Composer\\', 'Inertia\\',
        'Brick\\', 'Psr\\', 'GuzzleHttp\\', 'Carbon\\', 'Monolog\\', 'PHPUnit\\',
    ];

    private const MIGRATION_FILE = '/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+\.php$/';

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param  string  $packageDir  absolute path of the package directory
     * @param  string  $expectedName  vendor/name derived from the directory
     */
    public function validate(string $packageDir, string $expectedName, mixed $data, ValidationContext $context): ValidationResult
    {
        $this->errors = [];

        if (! is_array($data) || array_is_list($data)) {
            return ValidationResult::invalid(['The manifest must be a JSON object.']);
        }

        foreach (array_diff(array_keys($data), self::KEYS) as $unknown) {
            $this->errors[] = "Unknown manifest key \"{$unknown}\".";
        }

        $name = $this->string($data, 'name', 100, required: true);
        if ($name !== null && ! preg_match(self::NAME, $name)) {
            $this->errors[] = 'name must look like "vendor/package" (lowercase letters, digits and dashes).';
        } elseif ($name !== null && $name !== $expectedName) {
            $this->errors[] = "name \"{$name}\" does not match the package directory \"{$expectedName}\".";
        }

        $this->string($data, 'display_name', 120, required: true);
        $this->string($data, 'description', 500);
        $this->string($data, 'author', 120);
        $this->string($data, 'license', 60);

        $version = $this->string($data, 'version', 64, required: true);
        if ($version !== null && ! preg_match(self::SEMVER, $version)) {
            $this->errors[] = 'version must be a semantic version such as 1.2.0.';
        }

        if (($data['type'] ?? null) !== 'module') {
            $this->errors[] = 'type must be "module".';
        }

        $this->platform($data);
        $namespace = $this->autoload($packageDir, $data, $context, $name);
        $this->provider($packageDir, $data, $namespace);
        $this->requires($data, $name);
        $permissions = $this->permissions($data, $context, $name);
        $capabilities = $this->capabilities($data);
        $this->migrations($packageDir, $data, $capabilities, $context, $name);
        $this->routes($packageDir, $data, $capabilities);
        $this->assets($packageDir, $data);
        $this->settings($data, $capabilities);
        $this->navigation($data, $capabilities, $permissions, $context);

        return $this->errors === [] ? ValidationResult::valid(Manifest::fromArray($data)) : ValidationResult::invalid($this->errors);
    }

    /**
     * Resolves a manifest path inside the package. Absolute paths, `..`
     * segments and symlinks that point outside the package are refused.
     */
    public static function resolvePath(string $packageDir, string $relative, bool $directory): ?string
    {
        if ($relative === '' || strlen($relative) > 255 || str_contains($relative, "\0")
            || preg_match('#^([a-zA-Z]:|[/\\\\])#', $relative)
            || in_array('..', preg_split('#[/\\\\]#', $relative) ?: [], true)) {
            return null;
        }

        $root = realpath($packageDir);
        $target = realpath($packageDir.DIRECTORY_SEPARATOR.$relative);

        if ($root === false || $target === false || ! str_starts_with($target, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return ($directory ? is_dir($target) : is_file($target)) ? $target : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function platform(array $data): void
    {
        $constraint = $this->string($data, 'platform_version', 100, required: true);

        if ($constraint === null) {
            return;
        }

        if (! $this->validConstraint($constraint)) {
            $this->errors[] = 'platform_version is not a valid version constraint.';
        } elseif (! Semver::satisfies(Platform::VERSION, $constraint)) {
            $this->errors[] = "Requires platform version {$constraint}; this store runs ".Platform::VERSION.'.';
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function autoload(string $packageDir, array $data, ValidationContext $context, ?string $name): ?string
    {
        if (! isset($data['autoload'])) {
            return null;
        }

        $map = $data['autoload']['psr-4'] ?? null;

        if (! is_array($data['autoload']) || array_keys($data['autoload']) !== ['psr-4'] || ! is_array($map) || count($map) !== 1) {
            $this->errors[] = 'autoload must contain exactly one "psr-4" namespace mapping.';

            return null;
        }

        $namespace = (string) array_key_first($map);
        $path = $map[$namespace];

        if (! preg_match('/^[A-Z][A-Za-z0-9_]*(\\\\[A-Z][A-Za-z0-9_]*)+\\\\$/', $namespace)) {
            $this->errors[] = 'The autoload namespace must have at least two segments and end with a backslash, e.g. "Acme\\\\Reviews\\\\".';

            return null;
        }

        foreach (self::RESERVED_NAMESPACES as $reserved) {
            if (str_starts_with($namespace, $reserved)) {
                $this->errors[] = "The namespace {$namespace} is reserved.";

                return null;
            }
        }

        foreach ($context->namespaces as $other => $owner) {
            if ($owner !== $name && (str_starts_with($namespace, $other) || str_starts_with($other, $namespace))) {
                $this->errors[] = "The namespace {$namespace} overlaps with {$owner}.";
            }
        }

        if (! is_string($path) || self::resolvePath($packageDir, $path, directory: true) === null) {
            $this->errors[] = 'The autoload path must be an existing directory inside the package.';
        }

        return $namespace;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function provider(string $packageDir, array $data, ?string $namespace): void
    {
        if (! isset($data['provider'])) {
            return;
        }

        $provider = $data['provider'];

        if (! is_string($provider) || ! preg_match('/^[A-Z][A-Za-z0-9_]*(\\\\[A-Z][A-Za-z0-9_]*)+$/', $provider)) {
            $this->errors[] = 'provider must be a fully qualified class name.';

            return;
        }

        if ($namespace === null || ! str_starts_with($provider, $namespace)) {
            $this->errors[] = 'provider must be a class inside the module\'s autoload namespace.';

            return;
        }

        $base = (string) $data['autoload']['psr-4'][$namespace];
        $relative = rtrim($base, '/\\').'/'.str_replace('\\', '/', substr($provider, strlen($namespace))).'.php';

        if (self::resolvePath($packageDir, $relative, directory: false) === null) {
            $this->errors[] = "The provider class file {$relative} does not exist.";
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requires(array $data, ?string $name): void
    {
        $requires = $data['requires'] ?? [];

        if (! is_array($requires) || ($requires !== [] && array_is_list($requires))) {
            $this->errors[] = 'requires must be an object of "vendor/package": "constraint".';

            return;
        }

        foreach ($requires as $dependency => $constraint) {
            if (! preg_match(self::NAME, (string) $dependency) || $dependency === $name) {
                $this->errors[] = "requires: \"{$dependency}\" is not a valid module name.";
            } elseif (! is_string($constraint) || ! $this->validConstraint($constraint)) {
                $this->errors[] = "requires: the constraint for {$dependency} is not valid.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function permissions(array $data, ValidationContext $context, ?string $name): array
    {
        $permissions = $this->list($data, 'permissions', 50);
        $names = [];

        foreach ($permissions as $i => $permission) {
            if (! is_array($permission) || ! is_string($permission['name'] ?? null)
                || ! preg_match('/^[a-z][a-z0-9_-]*(\.[a-z][a-z0-9_-]*)+$/', $permission['name'])) {
                $this->errors[] = "permissions[{$i}].name must look like \"reviews.moderate\".";

                continue;
            }

            if (! is_string($permission['label'] ?? null) || $permission['label'] === '' || mb_strlen($permission['label']) > 120
                || ! is_string($permission['group'] ?? null) || $permission['group'] === '' || mb_strlen($permission['group']) > 60) {
                $this->errors[] = "permissions[{$i}] needs a label (max 120) and a group (max 60).";
            }

            $owner = $context->permissionOwners[$permission['name']] ?? null;

            if (in_array($permission['name'], $names, true)) {
                $this->errors[] = "Permission {$permission['name']} is declared twice.";
            } elseif ($owner !== null && $owner !== $name) {
                $this->errors[] = "Permission {$permission['name']} is already defined by {$owner}.";
            }

            $names[] = $permission['name'];
        }

        if ($names !== [] && ! in_array('permissions', (array) ($data['capabilities'] ?? []), true)) {
            $this->errors[] = 'Declaring permissions requires the "permissions" capability.';
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function capabilities(array $data): array
    {
        $capabilities = $this->list($data, 'capabilities', 30);

        foreach ($capabilities as $capability) {
            if (! is_string($capability) || ! array_key_exists($capability, Manifest::CAPABILITIES)) {
                $this->errors[] = 'Unknown capability "'.(is_string($capability) ? $capability : gettype($capability)).'".';
            }
        }

        if (count($capabilities) !== count(array_unique(array_filter($capabilities, 'is_string')))) {
            $this->errors[] = 'capabilities must not repeat.';
        }

        return array_values(array_filter($capabilities, 'is_string'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $capabilities
     */
    private function migrations(string $packageDir, array $data, array $capabilities, ValidationContext $context, ?string $name): void
    {
        if (! isset($data['migrations'])) {
            return;
        }

        $directory = is_string($data['migrations']) ? self::resolvePath($packageDir, $data['migrations'], directory: true) : null;

        if ($directory === null) {
            $this->errors[] = 'migrations must be an existing directory inside the package.';

            return;
        }

        if (! in_array('database.migrations', $capabilities, true)) {
            $this->errors[] = 'Declaring migrations requires the "database.migrations" capability.';
        }

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
            $basename = basename($file);
            $owner = $context->migrationOwners[$basename] ?? null;

            if (! preg_match(self::MIGRATION_FILE, $basename)) {
                $this->errors[] = "Migration file {$basename} must be named like 2026_01_31_000000_create_example_table.php.";
            } elseif ($owner !== null && $owner !== $name) {
                $this->errors[] = "Migration {$basename} has the same name as one from {$owner}.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $capabilities
     */
    private function routes(string $packageDir, array $data, array $capabilities): void
    {
        $routes = $data['routes'] ?? [];

        if (! is_array($routes) || ($routes !== [] && array_is_list($routes))) {
            $this->errors[] = 'routes must be an object with "admin" and/or "storefront" files.';

            return;
        }

        foreach ($routes as $area => $file) {
            if (! in_array($area, ['admin', 'storefront'], true)) {
                $this->errors[] = "Unknown route area \"{$area}\" (use admin or storefront).";
            } elseif (! is_string($file) || self::resolvePath($packageDir, $file, directory: false) === null) {
                $this->errors[] = "The {$area} routes file must exist inside the package.";
            } elseif (! in_array("{$area}.routes", $capabilities, true)) {
                $this->errors[] = "Declaring {$area} routes requires the \"{$area}.routes\" capability.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assets(string $packageDir, array $data): void
    {
        $assets = $data['assets'] ?? [];

        if (! is_array($assets) || ($assets !== [] && array_is_list($assets))) {
            $this->errors[] = 'assets must be an object.';

            return;
        }

        foreach ($assets as $kind => $path) {
            if (! in_array($kind, ['pages', 'slots'], true)) {
                $this->errors[] = "Unknown asset kind \"{$kind}\" (use pages or slots).";
            } elseif (! is_string($path) || self::resolvePath($packageDir, $path, directory: true) === null) {
                $this->errors[] = "The {$kind} asset directory must exist inside the package.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $capabilities
     */
    private function settings(array $data, array $capabilities): void
    {
        $fields = $this->list($data, 'settings', 50);
        $keys = [];

        if ($fields !== [] && ! in_array('settings', $capabilities, true)) {
            $this->errors[] = 'Declaring settings requires the "settings" capability.';
        }

        foreach ($fields as $i => $field) {
            $where = "settings[{$i}]";

            if (! is_array($field) || ! is_string($field['key'] ?? null) || ! preg_match('/^[a-z][a-z0-9_]{0,59}$/', $field['key'])) {
                $this->errors[] = "{$where}.key must be lowercase snake_case.";

                continue;
            }

            if (in_array($field['key'], $keys, true)) {
                $this->errors[] = "Setting {$field['key']} is declared twice.";
            }
            $keys[] = $field['key'];

            if (! is_string($field['label'] ?? null) || $field['label'] === '' || mb_strlen($field['label']) > 120) {
                $this->errors[] = "{$where}.label is required (max 120).";
            }

            if (isset($field['help']) && (! is_string($field['help']) || mb_strlen($field['help']) > 300)) {
                $this->errors[] = "{$where}.help must be text (max 300).";
            }

            $type = $field['type'] ?? null;

            if (! in_array($type, Manifest::SETTING_TYPES, true)) {
                $this->errors[] = "{$where}.type must be one of ".implode(', ', Manifest::SETTING_TYPES).'.';

                continue;
            }

            if ($type === 'select') {
                $options = $field['options'] ?? null;

                if (! is_array($options) || $options === [] || count($options) > 100
                    || array_filter($options, fn ($o) => ! is_array($o) || ! is_scalar($o['value'] ?? null) || ! is_string($o['label'] ?? null)) !== []) {
                    $this->errors[] = "{$where}.options must list 1-100 {\"value\", \"label\"} pairs.";

                    continue;
                }
            }

            foreach (['min', 'max', 'max_length'] as $limit) {
                if (isset($field[$limit]) && ! is_int($field[$limit])) {
                    $this->errors[] = "{$where}.{$limit} must be an integer.";
                }
            }

            if (array_key_exists('default', $field) && $field['default'] !== null
                && ModuleSettingsSchema::coerce($field, $field['default']) === null) {
                $this->errors[] = "{$where}.default does not match its type.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $capabilities
     * @param  list<string>  $permissions
     */
    private function navigation(array $data, array $capabilities, array $permissions, ValidationContext $context): void
    {
        $items = $this->list($data, 'admin_navigation', 10);

        if ($items === []) {
            return;
        }

        if (! in_array('admin.navigation', $capabilities, true) || ! isset($data['routes']['admin'])) {
            $this->errors[] = 'Admin navigation requires the "admin.navigation" capability and admin routes.';
        }

        foreach ($items as $i => $item) {
            $where = "admin_navigation[{$i}]";

            if (! is_array($item)) {
                $this->errors[] = "{$where} must be an object.";

                continue;
            }

            if (! is_string($item['key'] ?? null) || ! preg_match('/^[a-z0-9-]{1,40}$/', $item['key'])) {
                $this->errors[] = "{$where}.key must be lowercase letters, digits and dashes.";
            }

            if (! is_string($item['title'] ?? null) || $item['title'] === '' || mb_strlen($item['title']) > 60) {
                $this->errors[] = "{$where}.title is required (max 60).";
            }

            // Relative to the module's own admin route names; modules cannot link to other routes.
            if (! is_string($item['route'] ?? null) || ! preg_match('/^[a-z0-9_.-]{1,80}$/', $item['route'])) {
                $this->errors[] = "{$where}.route must be one of the module's admin route names (without prefix).";
            }

            if (isset($item['icon']) && (! is_string($item['icon']) || ! preg_match('/^[a-z0-9-]{1,40}$/', $item['icon']))) {
                $this->errors[] = "{$where}.icon must be a lucide icon name.";
            }

            if (isset($item['group']) && (! is_string($item['group']) || mb_strlen($item['group']) > 60)) {
                $this->errors[] = "{$where}.group must be text (max 60).";
            }

            if (isset($item['order']) && ! is_int($item['order'])) {
                $this->errors[] = "{$where}.order must be an integer.";
            }

            $permission = $item['permission'] ?? null;

            if ($permission === null) {
                $this->errors[] = "{$where}.permission is required: every admin page must be permission-protected.";
            } elseif (! is_string($permission) || (! in_array($permission, $permissions, true) && ($context->permissionOwners[$permission] ?? null) !== 'core')) {
                $this->errors[] = "{$where}.permission must be one of the module's permissions or a core permission.";
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

        $value = $data[$key];

        if (! is_string($value) || ($required && trim($value) === '') || mb_strlen($value) > $max) {
            $this->errors[] = "{$key} must be text of at most {$max} characters.";

            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<mixed>
     */
    private function list(array $data, string $key, int $max): array
    {
        $value = $data[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            $this->errors[] = "{$key} must be a list.";

            return [];
        }

        if (count($value) > $max) {
            $this->errors[] = "{$key} may contain at most {$max} entries.";
        }

        return $value;
    }

    private function validConstraint(string $constraint): bool
    {
        try {
            (new VersionParser)->parseConstraints($constraint);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
