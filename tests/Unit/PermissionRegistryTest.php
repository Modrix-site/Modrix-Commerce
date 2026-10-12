<?php

namespace Tests\Unit;

use App\Core\Authorization\CorePermissions;
use App\Core\Authorization\PermissionRegistry;
use App\Core\Authorization\SystemRoles;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PermissionRegistryTest extends TestCase
{
    public function test_core_permissions_include_the_specification_set()
    {
        $registry = new PermissionRegistry;
        CorePermissions::registerInto($registry);

        foreach ([
            'products.view', 'products.create', 'products.update', 'products.delete',
            'orders.view', 'orders.manage', 'orders.refund',
            'customers.view', 'customers.manage', 'inventory.view', 'inventory.manage',
            'settings.manage', 'extensions.view', 'extensions.install', 'extensions.enable',
            'extensions.disable', 'extensions.update', 'themes.manage', 'licenses.manage', 'marketplace.manage',
        ] as $permission) {
            $this->assertTrue($registry->has($permission), "Missing {$permission}");
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'no dot' => ['products'],
            'uppercase' => ['Products.view'],
            'wildcard' => ['products.*'],
            'empty segment' => ['products..view'],
            'spaces' => ['products. view'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected(string $name)
    {
        $this->expectException(InvalidArgumentException::class);
        (new PermissionRegistry)->register($name, 'Label', 'Group');
    }

    public function test_a_module_cannot_take_over_a_core_permission()
    {
        $registry = new PermissionRegistry;
        $registry->register('products.view', 'View products', 'Catalog');

        $this->expectException(InvalidArgumentException::class);
        $registry->register('products.view', 'Hijack', 'Catalog', 'vendor/evil-module');
    }

    public function test_system_role_defaults_are_least_privilege()
    {
        $registry = new PermissionRegistry;
        CorePermissions::registerInto($registry);
        $roles = SystemRoles::definitions($registry->names());

        $this->assertSame([], $roles[SystemRoles::OWNER]['permissions']);

        foreach ($roles['analyst']['permissions'] as $permission) {
            $this->assertStringEndsWith('.view', $permission);
        }
        $this->assertNotContains('users.view', $roles['analyst']['permissions']);

        foreach (['store-manager', 'catalog-manager', 'order-manager', 'support-agent', 'analyst'] as $role) {
            $this->assertNotContains('roles.manage', $roles[$role]['permissions'], $role);
            $this->assertNotContains('users.manage', $roles[$role]['permissions'], $role);
            $this->assertNotContains('extensions.install', $roles[$role]['permissions'], $role);
        }

        foreach ($roles as $name => $definition) {
            foreach ($definition['permissions'] as $permission) {
                $this->assertTrue($registry->has($permission), "{$name} references unknown {$permission}");
            }
        }
    }
}
