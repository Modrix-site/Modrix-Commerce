<?php

namespace App\Core\Authorization;

use InvalidArgumentException;

/**
 * The code-level source of truth for permission names.
 *
 * Core and modules register permissions here; `ecomodular:sync-permissions`
 * mirrors them into the `permissions` table so roles can reference them.
 * Gate checks consult this registry, so an unregistered ability is never
 * granted by role membership.
 */
final class PermissionRegistry
{
    /** @var array<string, array{name: string, label: string, group: string, source: string}> */
    private array $permissions = [];

    public function register(string $name, string $label, string $group, string $source = 'core'): void
    {
        if (! preg_match('/^[a-z][a-z0-9_-]*(\.[a-z][a-z0-9_-]*)+$/', $name)) {
            throw new InvalidArgumentException("Invalid permission name [{$name}].");
        }

        if (isset($this->permissions[$name]) && $this->permissions[$name]['source'] !== $source) {
            throw new InvalidArgumentException(
                "Permission [{$name}] is already registered by [{$this->permissions[$name]['source']}]."
            );
        }

        $this->permissions[$name] = compact('name', 'label', 'group', 'source');
    }

    public function has(string $name): bool
    {
        return isset($this->permissions[$name]);
    }

    /**
     * @return array<string, array{name: string, label: string, group: string, source: string}>
     */
    public function all(): array
    {
        return $this->permissions;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->permissions);
    }
}
