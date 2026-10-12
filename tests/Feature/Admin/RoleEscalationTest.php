<?php

namespace Tests\Feature\Admin;

use App\Core\Authorization\Actions\SetUserDisabled;
use App\Core\Authorization\Actions\SyncUserRoles;
use App\Core\Authorization\Models\Role;
use App\Core\Authorization\SystemRoles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Privilege-escalation invariants (ADR-004). Every case goes through the HTTP
 * layer so that controllers, validation and actions are all exercised.
 */
class RoleEscalationTest extends TestCase
{
    use RefreshDatabase;

    private User $delegate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();

        // A staff admin who can manage users and roles but holds few commerce permissions.
        $role = $this->roleWithPermissions('staff-admin', ['users.view', 'users.manage', 'roles.manage', 'products.view']);
        $this->delegate = User::factory()->create();
        $this->delegate->roles()->attach($role->id, ['created_at' => now()]);
    }

    private function roleId(string $name): int
    {
        return Role::query()->where('name', $name)->value('id');
    }

    public function test_cannot_grant_a_role_with_permissions_the_actor_lacks()
    {
        $target = $this->userWithRole('support-agent');

        $this->actingAsConfirmed($this->delegate)
            ->put(route('admin.users.roles.update', $target), ['roles' => [$this->roleId('administrator')]])
            ->assertSessionHasErrors('roles');

        $this->assertFalse($target->fresh()->hasRole('administrator'));
    }

    public function test_cannot_revoke_a_role_with_permissions_the_actor_lacks()
    {
        $target = $this->userWithRole('catalog-manager');

        $this->actingAsConfirmed($this->delegate)
            ->put(route('admin.users.roles.update', $target), ['roles' => []])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($target->fresh()->hasRole('catalog-manager'));
    }

    public function test_can_grant_a_role_whose_permissions_are_a_subset()
    {
        $viewer = $this->roleWithPermissions('product-viewer', ['products.view']);
        $target = User::factory()->create();

        $this->actingAsConfirmed($this->delegate)
            ->put(route('admin.users.roles.update', $target), ['roles' => [$viewer->id]])
            ->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->hasRole('product-viewer'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.roles_changed', 'subject_id' => $target->id]);
    }

    public function test_non_owner_cannot_grant_owner_role()
    {
        $administrator = $this->userWithRole('administrator');
        $target = $this->userWithRole('support-agent');

        $this->actingAsConfirmed($administrator)
            ->put(route('admin.users.roles.update', $target), ['roles' => [$this->roleId(SystemRoles::OWNER)]])
            ->assertSessionHasErrors('roles');

        $this->assertFalse($target->fresh()->isOwner());
    }

    public function test_non_owner_cannot_modify_an_owner_account()
    {
        $administrator = $this->userWithRole('administrator');
        $owner = $this->owner();

        $this->actingAsConfirmed($administrator)
            ->put(route('admin.users.status.update', $owner), ['disabled' => true])
            ->assertSessionHasErrors('user');

        $this->assertFalse($owner->fresh()->isDisabled());
    }

    public function test_owner_can_grant_owner_role()
    {
        $owner = $this->owner();
        $target = $this->userWithRole('administrator');

        $this->actingAsConfirmed($owner)
            ->put(route('admin.users.roles.update', $target), ['roles' => [$this->roleId(SystemRoles::OWNER)]])
            ->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->isOwner());
    }

    public function test_users_cannot_change_their_own_roles()
    {
        $administrator = $this->userWithRole('administrator');

        $this->actingAsConfirmed($administrator)
            ->put(route('admin.users.roles.update', $administrator), ['roles' => [$this->roleId('administrator'), $this->roleId('analyst')]])
            ->assertSessionHasErrors('roles');
    }

    public function test_owner_can_demote_another_owner_when_one_remains()
    {
        $owner = $this->owner();
        $secondOwner = $this->owner();

        $this->actingAsConfirmed($owner)
            ->put(route('admin.users.roles.update', $secondOwner), ['roles' => [$this->roleId('administrator')]])
            ->assertSessionHasNoErrors();

        $this->assertFalse($secondOwner->fresh()->isOwner());
    }

    /**
     * Over HTTP the self-edit rule already prevents an owner from removing the last
     * owner, so the invariant is verified directly on the actions (defence in depth).
     */
    public function test_last_active_owner_cannot_lose_role_or_be_disabled()
    {
        $lastActiveOwner = $this->owner();
        $disabledOwner = $this->owner(['disabled_at' => now()]);

        try {
            app(SyncUserRoles::class)->handle($disabledOwner, $lastActiveOwner, []);
            $this->fail('Expected the last owner to keep the owner role.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('roles', $e->errors());
        }

        try {
            app(SetUserDisabled::class)->handle($disabledOwner, $lastActiveOwner, true);
            $this->fail('Expected the last active owner to stay enabled.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('user', $e->errors());
        }

        $this->assertTrue($lastActiveOwner->fresh()->isOwner());
        $this->assertFalse($lastActiveOwner->fresh()->isDisabled());
    }

    public function test_cannot_add_unheld_permissions_to_a_role()
    {
        $this->actingAsConfirmed($this->delegate)
            ->post(route('admin.roles.store'), [
                'display_name' => 'Sneaky',
                'permissions' => ['products.view', 'settings.manage'],
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseMissing('roles', ['name' => 'sneaky']);
    }

    public function test_cannot_edit_a_role_holding_unheld_permissions()
    {
        $this->actingAsConfirmed($this->delegate)
            ->put(route('admin.roles.update', $this->roleId('catalog-manager')), [
                'display_name' => 'Catalog manager',
                'permissions' => ['products.view'],
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_owner_role_cannot_be_edited_or_deleted()
    {
        $owner = $this->owner();
        $ownerRoleId = $this->roleId(SystemRoles::OWNER);

        $this->actingAsConfirmed($owner)
            ->put(route('admin.roles.update', $ownerRoleId), ['display_name' => 'Boss', 'permissions' => []])
            ->assertSessionHasErrors('role');

        $this->actingAsConfirmed($owner)
            ->delete(route('admin.roles.destroy', $ownerRoleId))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $ownerRoleId, 'display_name' => 'Owner']);
    }

    public function test_system_roles_cannot_be_deleted_and_assigned_roles_are_protected()
    {
        $owner = $this->owner();

        $this->actingAsConfirmed($owner)
            ->delete(route('admin.roles.destroy', $this->roleId('analyst')))
            ->assertSessionHasErrors('role');

        $custom = $this->roleWithPermissions('temp', ['products.view']);
        $holder = User::factory()->create();
        $holder->roles()->attach($custom->id, ['created_at' => now()]);

        $this->actingAsConfirmed($owner)
            ->delete(route('admin.roles.destroy', $custom))
            ->assertSessionHasErrors('role');

        $holder->roles()->detach();

        $this->actingAsConfirmed($owner)
            ->delete(route('admin.roles.destroy', $custom))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $custom->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deleted']);
    }

    public function test_owner_can_create_custom_role()
    {
        $this->actingAsConfirmed($this->owner())
            ->post(route('admin.roles.store'), [
                'display_name' => 'Warehouse',
                'description' => 'Stock only',
                'permissions' => ['inventory.view', 'inventory.manage'],
            ])
            ->assertSessionHasNoErrors();

        $role = Role::query()->where('name', 'warehouse')->firstOrFail();
        $this->assertEqualsCanonicalizing(['inventory.view', 'inventory.manage'], $role->load('permissions')->permissionNames());
    }

    public function test_unknown_permissions_are_rejected()
    {
        $this->actingAsConfirmed($this->owner())
            ->post(route('admin.roles.store'), [
                'display_name' => 'Bogus',
                'permissions' => ['everything.allowed'],
            ])
            ->assertSessionHasErrors('permissions.0');
    }
}
