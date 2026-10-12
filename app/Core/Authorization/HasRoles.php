<?php

namespace App\Core\Authorization;

use App\Core\Authorization\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role-based access for the User model.
 *
 * Permission names are memoised per model instance; call refreshPermissions()
 * after changing role membership in the same request.
 */
trait HasRoles
{
    /** @var list<string>|null */
    private ?array $cachedPermissionNames = null;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('created_at');
    }

    public function hasRole(string $name): bool
    {
        return $this->roles->contains('name', $name);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(SystemRoles::OWNER);
    }

    public function isStaff(): bool
    {
        return $this->roles->isNotEmpty();
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    /**
     * Effective permission names. Owners receive every registered permission.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if ($this->cachedPermissionNames !== null) {
            return $this->cachedPermissionNames;
        }

        if ($this->isOwner()) {
            return $this->cachedPermissionNames = app(PermissionRegistry::class)->names();
        }

        $this->loadMissing('roles.permissions');

        /** @var list<string> $names */
        $names = $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $this->cachedPermissionNames = $names;
    }

    public function hasPermission(string $permission): bool
    {
        return ! $this->isDisabled() && in_array($permission, $this->permissionNames(), true);
    }

    public function refreshPermissions(): void
    {
        $this->cachedPermissionNames = null;
        $this->unsetRelation('roles');
    }
}
