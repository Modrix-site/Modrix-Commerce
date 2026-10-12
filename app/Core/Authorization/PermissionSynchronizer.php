<?php

namespace App\Core\Authorization;

use App\Core\Authorization\Models\Permission;
use App\Core\Authorization\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the PermissionRegistry into the database and creates any missing
 * system roles. Existing role/permission assignments are never removed, so
 * merchant customisations survive upgrades.
 */
final class PermissionSynchronizer
{
    public function __construct(private readonly PermissionRegistry $registry) {}

    /**
     * @return array{permissions_created: int, roles_created: int}
     */
    public function sync(): array
    {
        return DB::transaction(function () {
            $permissionsCreated = 0;

            foreach ($this->registry->all() as $definition) {
                $permission = Permission::query()->updateOrCreate(
                    ['name' => $definition['name']],
                    [
                        'display_name' => $definition['label'],
                        'group' => $definition['group'],
                        'source' => $definition['source'],
                    ],
                );

                if ($permission->wasRecentlyCreated) {
                    $permissionsCreated++;
                }
            }

            $rolesCreated = 0;
            $permissionIds = Permission::query()->pluck('id', 'name');

            foreach (SystemRoles::definitions($this->registry->names()) as $name => $definition) {
                $role = Role::query()->firstOrNew(['name' => $name]);
                $isNew = ! $role->exists;

                $role->display_name = $definition['display_name'];
                $role->description = $definition['description'];
                $role->is_system = true;
                $role->save();

                if ($isNew) {
                    $role->permissions()->sync(
                        $permissionIds->only($definition['permissions'])->values()->all()
                    );
                    $rolesCreated++;
                }
            }

            return ['permissions_created' => $permissionsCreated, 'roles_created' => $rolesCreated];
        });
    }
}
