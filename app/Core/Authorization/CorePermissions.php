<?php

namespace App\Core\Authorization;

/**
 * Permissions owned by the platform core, grouped for the role editor.
 */
final class CorePermissions
{
    /**
     * @return array<string, array<string, string>> group => [name => label]
     */
    public static function definitions(): array
    {
        return [
            'Catalog' => [
                'products.view' => 'View products',
                'products.create' => 'Create products',
                'products.update' => 'Update products',
                'products.delete' => 'Delete products',
                'media.manage' => 'Manage the media library',
            ],
            'Orders' => [
                'orders.view' => 'View orders',
                'orders.manage' => 'Manage orders',
                'orders.refund' => 'Issue refunds',
            ],
            'Customers' => [
                'customers.view' => 'View customers',
                'customers.manage' => 'Manage customers',
            ],
            'Inventory' => [
                'inventory.view' => 'View inventory',
                'inventory.manage' => 'Adjust inventory',
            ],
            'Discounts' => [
                'discounts.view' => 'View discounts',
                'discounts.manage' => 'Manage discounts',
            ],
            'Shipping & payments' => [
                'shipping.manage' => 'Manage shipping methods',
                'payments.manage' => 'Manage payment methods',
            ],
            'Reports' => [
                'reports.view' => 'View reports',
            ],
            'Extensions' => [
                'extensions.view' => 'View extensions',
                'extensions.install' => 'Install and uninstall extensions',
                'extensions.enable' => 'Enable extensions',
                'extensions.configure' => 'Configure extensions',
                'extensions.disable' => 'Disable extensions',
                'extensions.update' => 'Update extensions',
                'themes.manage' => 'Manage themes',
            ],
            'Marketplace' => [
                'marketplace.manage' => 'Manage marketplace',
                'licenses.manage' => 'Manage licenses',
            ],
            'Administration' => [
                'settings.manage' => 'Manage store settings',
                'settings.advanced' => 'Manage security settings and custom code',
                'users.view' => 'View staff users',
                'users.manage' => 'Manage staff users',
                'roles.manage' => 'Manage roles',
                'audit.view' => 'View audit log',
                'system.view' => 'View system status',
            ],
        ];
    }

    public static function registerInto(PermissionRegistry $registry): void
    {
        foreach (self::definitions() as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                $registry->register($name, $label, $group);
            }
        }
    }
}
