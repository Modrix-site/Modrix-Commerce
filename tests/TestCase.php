<?php

namespace Tests;

use App\Core\Authorization\Models\Permission;
use App\Core\Authorization\Models\Role;
use App\Core\Authorization\PermissionSynchronizer;
use App\Core\Authorization\SystemRoles;
use App\Core\Installation\InstallationState;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Sync permissions/system roles and mark the platform installed,
     * as `ecomodular:install` would.
     */
    protected function installPlatform(): void
    {
        app(PermissionSynchronizer::class)->sync();
        app(InstallationState::class)->markInstalled();
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::query()->where('name', $role)->firstOrFail()->id, ['created_at' => now()]);

        return $user->fresh();
    }

    protected function owner(array $attributes = []): User
    {
        return $this->userWithRole(SystemRoles::OWNER, $attributes);
    }

    /**
     * Creates a custom role with exactly the given permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function roleWithPermissions(string $name, array $permissions): Role
    {
        $role = new Role(['name' => $name, 'display_name' => ucfirst($name)]);
        $role->name = $name;
        $role->save();
        $role->permissions()->sync(
            Permission::query()->whereIn('name', $permissions)->pluck('id')
        );

        return $role;
    }

    /**
     * Act as the user with a fresh password confirmation (for password.confirm routes).
     */
    protected function actingAsConfirmed(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    }
}
