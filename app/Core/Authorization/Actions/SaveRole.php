<?php

namespace App\Core\Authorization\Actions;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\EscalationGuard;
use App\Core\Authorization\Exceptions\AuthorizationRuleViolation;
use App\Core\Authorization\Models\Permission;
use App\Core\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaveRole
{
    public function __construct(
        private readonly EscalationGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{display_name: string, description?: string|null, permissions: list<string>}  $data
     */
    public function handle(User $actor, ?Role $role, array $data): Role
    {
        $permissions = array_values(array_unique($data['permissions']));
        $this->guard->ensureCanEditRole($actor, $role, $permissions);

        return DB::transaction(function () use ($actor, $role, $data, $permissions) {
            $isNew = $role === null;
            $role ??= new Role;
            $before = $isNew ? [] : $role->load('permissions')->permissionNames();

            if ($isNew) {
                $name = Str::slug($data['display_name']);

                if ($name === '' || Role::query()->where('name', $name)->exists()) {
                    throw AuthorizationRuleViolation::make('display_name', 'A role with this name already exists.');
                }

                $role->name = $name;
                $role->is_system = false;
            }

            // System role names are stable identifiers; only their labels may change.
            $role->display_name = $data['display_name'];
            $role->description = $data['description'] ?? null;
            $role->save();

            $ids = Permission::query()->whereIn('name', $permissions)->pluck('id')->all();
            $role->permissions()->sync($ids);

            $this->audit->record($isNew ? 'role.created' : 'role.updated', $role, [
                'name' => $role->name,
                'permissions_added' => array_values(array_diff($permissions, $before)),
                'permissions_removed' => array_values(array_diff($before, $permissions)),
            ], $actor);

            return $role;
        });
    }
}
