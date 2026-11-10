<?php

namespace Tests\Feature\Storefront;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    private function shopper(): User
    {
        return User::factory()->create(['name' => 'Ana Buyer', 'email' => 'ana@example.test']);
    }

    public function test_overview_shows_counts_and_recent_orders(): void
    {
        $this->actingAs($this->shopper());
        $this->post(route('account.addresses.store'), [
            'first_name' => 'Ana', 'last_name' => 'Buyer', 'line1' => '1 Test Street',
            'city' => 'Manila', 'postal_code' => '1000', 'country_code' => 'PH', 'region' => 'Metro Manila',
            'is_default_shipping' => '1',
        ])->assertSessionHasNoErrors();

        $this->get(route('account.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('storefront/account/index')
            ->where('stats', ['orders' => 0, 'unread_notifications' => 0, 'addresses' => 1])
            ->where('recentOrders', [])
            ->has('customer.member_since')
            ->where('defaultAddress.city', 'Manila'));
    }

    public function test_profile_is_managed_inside_the_storefront(): void
    {
        $this->actingAs($this->shopper());

        $this->get(route('account.profile'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('storefront/account/profile')
            ->has('storefront'));

        $this->patch(route('account.profile.update'), ['name' => 'Ana B. Buyer', 'email' => 'ana@example.test'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.profile'));
        $this->assertSame('Ana B. Buyer', User::query()->where('email', 'ana@example.test')->value('name'));
    }

    public function test_security_asks_for_the_password_then_opens_in_the_storefront(): void
    {
        $this->actingAs($this->shopper());

        $this->get(route('account.security'))->assertRedirect(route('account.password.confirm'));
        $this->get(route('account.password.confirm'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('storefront/auth/confirm-password')->has('storefront'));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('account.security'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/account/security')
                ->has('storefront')
                ->has('passwordRules'));
    }

    public function test_staff_settings_pages_are_unchanged(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->component('settings/profile'));
        $this->patch(route('profile.update'), ['name' => 'Owner', 'email' => $owner->email])
            ->assertRedirect(route('profile.edit'));
    }

    public function test_customer_change_password_page_is_inside_the_storefront(): void
    {
        $this->get(route('account.password'))->assertRedirect(route('login'));
        $this->actingAs($this->shopper())->get(route('account.password'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('storefront/account/password')->has('storefront.theme')->has('passwordRules'));
    }

    public function test_customer_can_change_password_using_their_current_password(): void
    {
        $user = $this->shopper();
        $this->actingAs($user)->from(route('account.password'))->put(route('account.password.update'), [
            'current_password' => 'password', 'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('account.password'));
        $this->assertTrue(Hash::check('new-secure-password-123', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_customer_change_password_rejects_wrong_current_password_and_mismatched_confirmation(): void
    {
        $user = $this->shopper();
        $oldHash = $user->password;
        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'wrong-password', 'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ])->assertSessionHasErrors('current_password');
        $this->put(route('account.password.update'), [
            'current_password' => 'password', 'password' => 'new-secure-password-123',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');
        $this->assertSame($oldHash, $user->fresh()->password);
    }
}
