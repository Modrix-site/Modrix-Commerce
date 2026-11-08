<?php

namespace App\Core\Extensions;

/**
 * A validated `module.json`. Data only: reading a manifest never executes
 * package code. Construct through ManifestValidator, or from a stored,
 * previously validated array.
 */
final class Manifest
{
    public const FILE = 'module.json';

    /** Largest manifest accepted, in bytes. */
    public const MAX_BYTES = 65536;

    /**
     * Declared capabilities. Declarative manifest sections require the
     * matching capability; the others document what the module's PHP code
     * uses (PHP code is not sandboxed, see ADR-029).
     */
    public const CAPABILITIES = [
        'admin.routes' => 'Adds admin pages',
        'admin.navigation' => 'Adds admin menu items',
        'storefront.routes' => 'Adds storefront pages or endpoints',
        'storefront.slots' => 'Shows content inside storefront pages',
        'dashboard.widgets' => 'Adds dashboard widgets',
        'database.migrations' => 'Creates database tables',
        'settings' => 'Has merchant settings',
        'permissions' => 'Defines staff permissions',
        'events.listen' => 'Reacts to store events',
        'scheduled.tasks' => 'Runs scheduled tasks',
        'console.commands' => 'Adds console commands',
        'notifications' => 'Sends notifications',
        'checkout.validators' => 'Can block checkout',
        'pricing.steps' => 'Changes cart pricing',
        'catalog.sale_prices' => 'Sets temporary sale prices on products',
        'catalog.image_urls' => 'Replaces product image files on the storefront',
        'payment.gateway' => 'Adds a payment method',
        'shipping.rates' => 'Adds shipping rates',
        'tax.provider' => 'Replaces tax calculation',
        'search.provider' => 'Replaces storefront search',
        'sitemap.provider' => 'Contributes the storefront XML sitemap',
        'product.labels' => 'Badges storefront product cards',
        'content_security' => 'Loads content from outside the store',
    ];

    /** Content-Security-Policy directives a module may extend (with exact https origins). */
    public const CONTENT_SECURITY_DIRECTIVES = ['script-src', 'frame-src', 'connect-src', 'img-src', 'form-action'];

    public const SETTING_TYPES = ['boolean', 'string', 'text', 'integer', 'select', 'email'];

    /**
     * @param  array<string, string>  $autoload  namespace => relative path
     * @param  array<string, string>  $requires  module name => version constraint
     * @param  list<array{name: string, label: string, group: string}>  $permissions
     * @param  list<string>  $capabilities
     * @param  array{admin?: string, storefront?: string}  $routes
     * @param  array{pages?: string, slots?: string}  $assets
     * @param  list<array<string, mixed>>  $settings
     * @param  list<array{key: string, title: string, route: string, icon: string, group: string, permission: string|null, order: int}>  $adminNavigation
     * @param  array<string, list<string>>  $contentSecurity  directive => https origins
     */
    public function __construct(
        public readonly string $name,
        public readonly string $displayName,
        public readonly ?string $description,
        public readonly string $version,
        public readonly string $platformVersion,
        public readonly ?string $author,
        public readonly ?string $license,
        public readonly array $autoload,
        public readonly ?string $provider,
        public readonly array $requires,
        public readonly array $permissions,
        public readonly array $capabilities,
        public readonly ?string $migrations,
        public readonly array $routes,
        public readonly array $assets,
        public readonly array $settings,
        public readonly array $adminNavigation,
        public readonly array $contentSecurity = [],
        /** Lucide icon name shown for the module in the admin (default "puzzle"). */
        public readonly string $icon = 'puzzle',
        /** Optional brand colour (6-digit hex) used as the module's accent. */
        public readonly ?string $accent = null,
        public readonly ?string $logo = null,
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
            autoload: (array) ($data['autoload']['psr-4'] ?? []),
            provider: isset($data['provider']) ? (string) $data['provider'] : null,
            requires: (array) ($data['requires'] ?? []),
            permissions: array_values((array) ($data['permissions'] ?? [])),
            capabilities: array_values((array) ($data['capabilities'] ?? [])),
            migrations: isset($data['migrations']) ? (string) $data['migrations'] : null,
            routes: (array) ($data['routes'] ?? []),
            assets: (array) ($data['assets'] ?? []),
            settings: array_values((array) ($data['settings'] ?? [])),
            adminNavigation: array_values(array_map(fn (array $item) => [
                'key' => (string) $item['key'],
                'title' => (string) $item['title'],
                'route' => (string) $item['route'],
                'icon' => (string) ($item['icon'] ?? 'puzzle'),
                'group' => (string) ($item['group'] ?? 'Extensions'),
                'permission' => isset($item['permission']) ? (string) $item['permission'] : null,
                'order' => (int) ($item['order'] ?? 500),
            ], (array) ($data['admin_navigation'] ?? []))),
            contentSecurity: array_map(fn ($origins) => array_values((array) $origins), (array) ($data['content_security'] ?? [])),
            icon: isset($data['icon']) ? (string) $data['icon'] : 'puzzle',
            accent: isset($data['accent']) ? (string) $data['accent'] : null,
            logo: isset($data['logo']) ? (string) $data['logo'] : null,
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
     * Route name prefix for the module's admin routes, e.g. "admin.acme.reviews.".
     */
    public function adminRoutePrefix(): string
    {
        return "admin.{$this->vendor()}.{$this->package()}.";
    }

    /**
     * Route name prefix for the module's storefront routes, e.g. "acme.reviews.".
     */
    public function storefrontRoutePrefix(): string
    {
        return "{$this->vendor()}.{$this->package()}.";
    }

    /**
     * URL prefix for the module's routes, e.g. "m/acme/reviews".
     */
    public function urlPrefix(): string
    {
        return "m/{$this->vendor()}/{$this->package()}";
    }

    /**
     * Settings key namespace, e.g. "extension.acme.reviews".
     */
    public function settingsPrefix(): string
    {
        return "extension.{$this->vendor()}.{$this->package()}";
    }

    /**
     * Inertia page name prefix, e.g. "module/acme/reviews/".
     */
    public function pagePrefix(): string
    {
        return "module/{$this->name}/";
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function setting(string $key): ?array
    {
        foreach ($this->settings as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }

        return null;
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
            'type' => 'module',
            'platform_version' => $this->platformVersion,
            'author' => $this->author,
            'license' => $this->license,
            'autoload' => $this->autoload === [] ? null : ['psr-4' => $this->autoload],
            'provider' => $this->provider,
            'requires' => $this->requires,
            'permissions' => $this->permissions,
            'capabilities' => $this->capabilities,
            'migrations' => $this->migrations,
            'routes' => $this->routes,
            'assets' => $this->assets,
            'settings' => $this->settings,
            'admin_navigation' => $this->adminNavigation,
            'content_security' => $this->contentSecurity === [] ? null : $this->contentSecurity,
            'icon' => $this->icon,
            'accent' => $this->accent,
            ...($this->logo !== null ? ['logo' => $this->logo] : []),
        ], fn ($value) => $value !== null);
    }
}
