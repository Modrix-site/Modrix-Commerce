<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_customers_cannot_enter_the_admin_area()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_unverified_staff_are_sent_to_email_verification()
    {
        $this->actingAs($this->userWithRole('administrator', ['email_verified_at' => null]))
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function routeMatrix(): array
    {
        return [
            'owner sees users' => ['owner', 'admin.users.index', 200],
            'administrator sees users' => ['administrator', 'admin.users.index', 200],
            'store manager cannot see users' => ['store-manager', 'admin.users.index', 403],
            'support agent cannot see users' => ['support-agent', 'admin.users.index', 403],
            'administrator manages roles' => ['administrator', 'admin.roles.index', 200],
            'catalog manager cannot manage roles' => ['catalog-manager', 'admin.roles.index', 403],
            'administrator edits settings' => ['administrator', 'admin.settings.store.edit', 200],
            'order manager cannot edit settings' => ['order-manager', 'admin.settings.store.edit', 403],
            'administrator views audit' => ['administrator', 'admin.audit-logs.index', 200],
            'analyst cannot view audit' => ['analyst', 'admin.audit-logs.index', 403],
            'owner views system' => ['owner', 'admin.system.status', 200],
            'store manager cannot view system' => ['store-manager', 'admin.system.status', 403],
            'every staff role sees dashboard' => ['analyst', 'admin.dashboard', 200],
        ];
    }

    #[DataProvider('routeMatrix')]
    public function test_route_permissions_are_enforced_server_side(string $role, string $route, int $status)
    {
        $this->actingAsConfirmed($this->userWithRole($role))
            ->get(route($route))
            ->assertStatus($status);
    }

    /**
     * @return list<string>
     */
    private function navigationKeysFor(User $user): array
    {
        $keys = [];

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertInertia(function (Assert $page) use (&$keys) {
                $keys = collect($page->toArray()['props']['adminNavigation'])
                    ->flatMap(fn ($g) => collect($g['items'])->pluck('key'))
                    ->values()
                    ->all();
            });

        return $keys;
    }

    public function test_navigation_is_filtered_by_permission()
    {
        $keys = $this->navigationKeysFor($this->userWithRole('store-manager'));

        foreach (['dashboard', 'orders', 'customers', 'discounts', 'reports', 'products', 'inventory', 'shipping'] as $expected) {
            $this->assertContains($expected, $keys);
        }

        foreach (['users', 'roles', 'settings', 'audit', 'system', 'taxes', 'payments'] as $hidden) {
            $this->assertNotContains($hidden, $keys);
        }

        $this->assertSame(['dashboard', 'orders', 'customers'], $this->navigationKeysFor($this->userWithRole('support-agent')));
    }

    public function test_owner_navigation_includes_every_section()
    {
        $keys = $this->navigationKeysFor($this->owner());

        foreach (['dashboard', 'orders', 'customers', 'discounts', 'reports', 'products', 'categories', 'attributes', 'inventory', 'shipping', 'taxes', 'payments', 'users', 'roles', 'settings', 'audit', 'system'] as $expected) {
            $this->assertContains($expected, $keys);
        }
    }

    public function test_page_props_never_include_role_relations_or_secrets()
    {
        $this->actingAs($this->owner())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('auth.user.roles')
                ->missing('auth.user.password')
                ->missing('auth.user.two_factor_secret')
                ->missing('auth.user.remember_token'));
    }

    public function test_user_management_requires_recent_password_confirmation()
    {
        $this->actingAs($this->owner())
            ->get(route('admin.users.create'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_disabled_staff_are_logged_out_on_next_request()
    {
        $user = $this->userWithRole('administrator');
        $user->forceFill(['disabled_at' => now()])->save();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_disabled_users_cannot_log_in()
    {
        $user = $this->userWithRole('administrator', ['disabled_at' => now()]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
