<?php

namespace Tests\Feature\Pages;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Marketplace\Models\License;
use Marketplace\Models\LicenseActivation;
use Marketplace\Models\User;
use Marketplace\Services\Licensing;
use Tests\Support\Fixtures;
use Tests\TestCase;

/** Registration, sign-in and the account page with licenses. */
class AccountTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    public function test_register_then_sign_in_and_out()
    {
        $this->post('/account/register', ['name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'Secret-pass-1', 'password_confirmation' => 'Secret-pass-1', 'next' => '/account'])
            ->assertRedirect('/account');
        $this->assertAuthenticated();

        $this->post('/account/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->post('/account/login', ['email' => 'ana@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/account/login', ['email' => 'ana@example.test', 'password' => 'Secret-pass-1'])->assertRedirect('/account');
        $this->assertAuthenticated();
    }

    public function test_registration_needs_matching_passwords_and_a_new_email()
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->post('/account/register', ['name' => 'Ana', 'email' => 'taken@example.test', 'password' => 'Secret-pass-1', 'password_confirmation' => 'Secret-pass-1'])->assertSessionHasErrors('email');
        $this->post('/account/register', ['name' => 'Ana', 'email' => 'new@example.test', 'password' => 'Secret-pass-1', 'password_confirmation' => 'Other-pass-1'])->assertSessionHasErrors('password');
    }

    public function test_the_account_needs_sign_in()
    {
        $this->get('/account')->assertRedirect('/account/login?next=%2Faccount');
    }

    public function test_generating_a_free_license_shows_the_key()
    {
        $user = $this->customer();
        $free = $this->package(['name' => 'Free Labels']);

        $this->actingAs($user)->post('/account/licenses', ['product' => $free->slug])->assertRedirect('/account');

        $key = License::query()->where('user_id', $user->id)->value('key');
        $this->assertNotNull($key);
        $this->actingAs($user)->get('/account')->assertOk()->assertSee($key)->assertSee('Free Labels');
    }

    public function test_paid_packages_need_a_purchase_before_a_license()
    {
        $paid = $this->package(['is_free' => false, 'price' => '49.00']);

        $this->actingAs($this->customer())->post('/account/licenses', ['product' => $paid->slug])->assertSessionHasErrors('product');
        $this->assertSame(0, License::query()->count());
    }

    public function test_releasing_a_store_frees_the_key_and_only_for_its_owner()
    {
        $user = $this->customer();
        $license = app(Licensing::class)->issue($user, $this->package(), 'free');
        LicenseActivation::query()->create(['license_id' => $license->id, 'instance_id' => 'store-a', 'active' => true, 'activated_at' => now()]);

        $this->actingAs($this->customer())->post('/account/licenses/release', ['license_id' => $license->id])->assertForbidden();
        $this->assertNotNull($license->activeActivation());

        $this->actingAs($user)->post('/account/licenses/release', ['license_id' => $license->id])->assertRedirect('/account');
        $this->assertNull($license->activeActivation());
    }
}
