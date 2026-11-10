<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeparateLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_shoppers_sign_in_inside_the_storefront(): void
    {
        $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('storefront/auth/login')
            ->has('storefront')
            ->where('canRegister', true));
    }

    public function test_shoppers_register_inside_the_storefront(): void
    {
        $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('storefront/auth/register')
            ->has('storefront'));
    }

    public function test_staff_have_their_own_login_page(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('canRegister', false)
            ->missing('storefront'));
    }

    public function test_guests_are_sent_to_the_matching_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('account.show'))->assertRedirect(route('login'));
    }

    public function test_signed_in_users_skip_both_login_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('login'))->assertRedirect();
        $this->get(route('admin.login'))->assertRedirect();
    }

    public function test_staff_sign_in_from_the_admin_login_lands_on_the_dashboard(): void
    {
        $owner = $this->owner();

        $this->from(route('admin.login'))->post(route('login.store'), [
            'email' => $owner->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertRedirect(route('admin.dashboard'));
    }
}
