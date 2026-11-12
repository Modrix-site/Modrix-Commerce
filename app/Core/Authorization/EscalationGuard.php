<?php

namespace App\Core\Authorization;

use App\Core\Authorization\Exceptions\AuthorizationRuleViolation;
use App\Core\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Invariants that prevent privilege escalation through role management.
 *
 * 1. An actor may only grant or revoke roles whose permissions are a subset of their own.
 * 2. Only owners may grant or revoke the owner role, or modify an owner's account.
 * 3. Nobody may change their own roles or disable themselves.
 * 4. The last active owner can never lose the role or be disabled.
 * 5. Nobody may edit the owner role, and a role may only be edited by an actor
 *    who holds every permission it has before and after the change.
 */
final class EscalationGuard
{
    /**
     * @param  Collection<int, Role>  $roles
     */
    public function ensureCanGrantRoles(User $actor, Collection $roles, string $field = 'roles'): void
    {
        foreach ($roles as $role) {
            if ($role->isOwner()) {
                if (! $actor->isOwner()) {
                    throw AuthorizationRuleViolation::make($field, 'Only an owner can grant or revoke the owner role.');
                }

                continue;
            }

            $role->loadMissing('permissions');
            $this->ensureHoldsPermissions($actor, $role->permissionNames(), $field);
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    public function ensureHoldsPermissions(User $actor, array $permissions, string $field = 'permissions'): void
    {
        if ($actor->isOwner()) {
            return;
        }

        $missing = array_values(array_diff($permissions, $actor->permissionNames()));

        if ($missing !== []) {
            throw AuthorizationRuleViolation::make(
                $field,
                'You cannot grant permissions you do not hold: '.implode(', ', $missing).'.',
            );
        }
    }

    public function ensureNotSelf(User $actor, User $target, string $field, string $message): void
    {
        if ($actor->is($target)) {
            throw AuthorizationRuleViolation::make($field, $message);
        }
    }

    public function ensureCanManageAccount(User $actor, User $target, string $field = 'user'): void
    {
        if ($target->isOwner() && ! $actor->isOwner()) {
            throw AuthorizationRuleViolation::make($field, 'Only an owner can modify an owner account.');
        }
    }

    public function ensureNotLastOwner(User $target, string $field, string $message): void
    {
        if (! $target->isOwner()) {
            return;
        }

        $activeOwners = User::query()
            ->whereNull('disabled_at')
            ->whereHas('roles', fn ($q) => $q->where('name', SystemRoles::OWNER))
            ->lockForUpdate()
            ->count();

        if ($activeOwners <= 1) {
            throw AuthorizationRuleViolation::make($field, $message);
        }
    }

    /**
     * @param  list<string>  $newPermissions
     */
    public function ensureCanEditRole(User $actor, ?Role $role, array $newPermissions): void
    {
        if ($role?->isOwner()) {
            throw AuthorizationRuleViolation::make('role', 'The owner role cannot be edited.');
        }

        if ($role !== null) {
            $role->loadMissing('permissions');
            $this->ensureHoldsPermissions($actor, $role->permissionNames(), 'role');
        }

        $this->ensureHoldsPermissions($actor, $newPermissions);
    }
}
