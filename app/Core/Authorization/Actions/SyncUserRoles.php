<?php

namespace App\Core\Authorization\Actions;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\EscalationGuard;
use App\Core\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SyncUserRoles
{
    public function __construct(
        private readonly EscalationGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>  $roleIds
     */
    public function handle(User $actor, User $target, array $roleIds): void
    {
        $this->guard->ensureNotSelf($actor, $target, 'roles', 'You cannot change your own roles.');
        $this->guard->ensureCanManageAccount($actor, $target, 'roles');

        DB::transaction(function () use ($actor, $target, $roleIds) {
            $target->load('roles.permissions');

            $current = $target->roles;
            $requested = Role::query()->with('permissions')->whereKey($roleIds)->get();

            $added = $requested->diff($current);
            $removed = $current->diff($requested);

            // Both granting and revoking a role require holding its permissions.
            $this->guard->ensureCanGrantRoles($actor, $added->merge($removed));

            if ($removed->contains(fn (Role $role) => $role->isOwner())) {
                $this->guard->ensureNotLastOwner($target, 'roles', 'The last owner cannot lose the owner role.');
            }

            if ($added->isEmpty() && $removed->isEmpty()) {
                return;
            }

            $target->roles()->sync(
                $requested->mapWithKeys(fn (Role $role) => [$role->id => ['created_at' => now()]])->all()
            );
            $target->refreshPermissions();

            $this->audit->record('user.roles_changed', $target, [
                'added' => $added->pluck('name')->values()->all(),
                'removed' => $removed->pluck('name')->values()->all(),
            ], $actor);
        });
    }
}
