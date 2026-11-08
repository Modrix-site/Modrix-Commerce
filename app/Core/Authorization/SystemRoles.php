<?php

namespace App\Core\Authorization;

/**
 * Built-in roles. Default permissions are applied only when a role is first
 * created so that merchant edits to system roles are never overwritten.
 */
final class SystemRoles
{
    public const OWNER = 'owner';

    /**
     * @param  list<string>  $allPermissions
     * @return array<string, array{display_name: string, description: string, permissions: list<string>}>
     */
    public static function definitions(array $allPermissions): array
    {
        $readOnly = array_values(array_filter(
            $allPermissions,
            fn (string $p) => str_ends_with($p, '.view')
                && ! in_array($p, ['users.view', 'audit.view', 'system.view', 'extensions.view'], true),
        ));

        return [
            self::OWNER => [
                'display_name' => 'Owner',
                'description' => 'Full access to everything. Only owners can grant or revoke the owner role.',
                'permissions' => [], // implicit: Gate::before grants every registered ability
            ],
            'administrator' => [
                'display_name' => 'Administrator',
                'description' => 'Manages the store, staff, settings and extensions.',
                // Replacing the platform's own code is for owners only.
                'permissions' => array_values(array_diff($allPermissions, ['system.update'])),
            ],
            'store-manager' => [
                'display_name' => 'Store manager',
                'description' => 'Runs day-to-day commerce operations.',
                'permissions' => [
                    'products.view', 'products.create', 'products.update', 'products.delete', 'media.manage',
                    'orders.view', 'orders.manage', 'orders.refund',
                    'customers.view', 'customers.manage',
                    'inventory.view', 'inventory.manage',
                    'discounts.view', 'discounts.manage',
                    'shipping.manage', 'reports.view',
                ],
            ],
            'catalog-manager' => [
                'display_name' => 'Catalog manager',
                'description' => 'Maintains products, categories and stock levels.',
                'permissions' => [
                    'products.view', 'products.create', 'products.update', 'products.delete', 'media.manage',
                    'inventory.view', 'inventory.manage',
                ],
            ],
            'order-manager' => [
                'display_name' => 'Order manager',
                'description' => 'Processes, fulfils and refunds orders.',
                'permissions' => [
                    'orders.view', 'orders.manage', 'orders.refund',
                    'customers.view', 'inventory.view',
                ],
            ],
            'support-agent' => [
                'display_name' => 'Support agent',
                'description' => 'Helps customers with their accounts and orders.',
                'permissions' => ['orders.view', 'customers.view', 'customers.manage'],
            ],
            'analyst' => [
                'display_name' => 'Read-only analyst',
                'description' => 'Read-only access to commerce data and reports.',
                'permissions' => $readOnly,
            ],
        ];
    }
}
