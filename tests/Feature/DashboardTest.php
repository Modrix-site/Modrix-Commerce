<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_customers_land_on_their_account_page()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('account.show'));
    }

    public function test_staff_land_on_the_admin_dashboard()
    {
        $this->actingAs($this->userWithRole('support-agent'))
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }
}
