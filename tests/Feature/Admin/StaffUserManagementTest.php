<?php

namespace Tests\Feature\Admin;

use App\Core\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    public function test_staff_list_excludes_customers_and_supports_search()
    {
        $owner = $this->owner(['name' => 'Olivia Owner']);
        $this->userWithRole('support-agent', ['name' => 'Sam Support']);
        User::factory()->create(['name' => 'Carl Customer']);

        $this->actingAs($owner)
            ->get(route('admin.users.index', ['search' => 'sam']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Sam Support'));

        $this->actingAs($owner)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 2));
    }

    public function test_creating_staff_user_sends_password_setup_link_and_audits()
    {
        Notification::fake();
        $owner = $this->owner();
        $role = Role::query()->where('name', 'order-manager')->firstOrFail();

        $this->actingAsConfirmed($owner)
            ->post(route('admin.users.store'), [
                'name' => 'New Staff',
                'email' => 'New.Staff@example.com',
                'roles' => [$role->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user = User::query()->where('email', 'new.staff@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('order-manager'));
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'subject_id' => $user->id, 'actor_id' => $owner->id]);
    }

    public function test_creating_staff_user_requires_a_role()
    {
        $this->actingAsConfirmed($this->owner())
            ->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com'])
            ->assertSessionHasErrors('roles');
    }

    public function test_users_without_manage_permission_cannot_create_staff()
    {
        $this->actingAsConfirmed($this->userWithRole('analyst'))
            ->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com', 'roles' => [1]])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_disabling_and_enabling_a_user()
    {
        $owner = $this->owner();
        $target = $this->userWithRole('support-agent');

        $this->actingAsConfirmed($owner)
            ->put(route('admin.users.status.update', $target), ['disabled' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->isDisabled());
        $this->assertFalse($target->fresh()->can('orders.view'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.disabled', 'subject_id' => $target->id]);

        $this->actingAsConfirmed($owner)
            ->put(route('admin.users.status.update', $target), ['disabled' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($target->fresh()->isDisabled());
    }

    public function test_users_cannot_disable_themselves()
    {
        $owner = $this->owner();

        $this->actingAsConfirmed($owner)
            ->put(route('admin.users.status.update', $owner), ['disabled' => true])
            ->assertSessionHasErrors('user');

        $this->assertFalse($owner->fresh()->isDisabled());
    }

    public function test_removing_all_roles_turns_staff_into_customer()
    {
        $owner = $this->owner();
        $target = $this->userWithRole('support-agent');

        $this->actingAsConfirmed($owner)
            ->put(route('admin.users.roles.update', $target), [])
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse($target->fresh()->isStaff());
    }
}
