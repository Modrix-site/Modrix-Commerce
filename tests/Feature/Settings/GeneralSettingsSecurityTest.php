<?php

namespace Tests\Feature\Settings;

use App\Core\Settings\GeneralSettings;
use App\Models\User;
use App\Providers\GeneralSettingsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class GeneralSettingsSecurityTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
    }

    private function security(User $actor, array $values)
    {
        return $this->actingAs($actor)->put(route('admin.settings.general.security'), $values + [
            'registration_enabled' => true, 'require_two_factor' => false, 'session_lifetime' => null, 'login_attempts' => 5, 'password_min' => null,
        ]);
    }

    private function ownerWithTwoFactor(): User
    {
        $owner = $this->owner();
        $owner->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

        return $owner;
    }

    public function test_requiring_two_factor_is_refused_while_the_owner_has_none()
    {
        $this->security($this->owner(), ['require_two_factor' => true])->assertSessionHasErrors('require_two_factor');
        $this->assertFalse(app(GeneralSettings::class)->security()['require_two_factor']);
    }

    public function test_staff_without_two_factor_are_sent_to_turn_it_on()
    {
        $this->security($this->ownerWithTwoFactor(), ['require_two_factor' => true])->assertSessionHasNoErrors();

        $staff = $this->userWithRole('store-manager');
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertRedirect(route('security.edit'));
        // Their security page stays reachable (it may ask to confirm the password first).
        $this->assertStringNotContainsString('/admin', (string) $this->actingAs($staff)->get(route('security.edit'))->headers->get('Location'));
        $this->actingAs($this->ownerWithTwoFactor())->get(route('admin.dashboard'))->assertOk();
    }

    public function test_the_session_lifetime_and_password_minimum_apply()
    {
        $this->security($this->ownerWithTwoFactor(), ['session_lifetime' => 45, 'password_min' => 20])->assertSessionHasNoErrors();

        // The next request boots with the saved lifetime.
        (new GeneralSettingsServiceProvider($this->app))->boot();
        $this->assertSame(45, config('session.lifetime'));
        $this->assertTrue(Validator::make(['password' => 'short-password-1'], ['password' => Password::defaults()])->fails());
        $this->assertFalse(Validator::make(['password' => 'a-much-longer-password-1'], ['password' => Password::defaults()])->fails());
    }

    public function test_sign_in_attempts_are_limited_as_configured()
    {
        $this->security($this->ownerWithTwoFactor(), ['login_attempts' => 3]);
        auth()->logout();
        $user = User::factory()->create(['password' => Hash::make('right-password')]);

        foreach (range(1, 3) as $i) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertStatus(302);
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_sign_up_can_be_turned_off(): void
    {
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('canRegister', true));
        $this->get(route('register'))->assertOk();

        $this->security($this->ownerWithTwoFactor(), ['registration_enabled' => false])->assertSessionHasNoErrors();

        auth()->logout();

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('canRegister', false));
        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.store'), [
            'name' => 'New Customer',
            'email' => 'new-customer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'new-customer@example.com']);
    }

    public function test_values_are_validated()
    {
        $this->security($this->ownerWithTwoFactor(), ['session_lifetime' => 5, 'login_attempts' => 100, 'password_min' => 4])
            ->assertSessionHasErrors(['session_lifetime', 'login_attempts', 'password_min']);
    }

    public function test_custom_code_is_added_to_storefront_pages_with_the_nonce_and_its_hosts_to_the_csp()
    {
        $this->actingAs($this->owner())->put(route('admin.settings.general.scripts'), [
            'head_enabled' => true, 'head' => '<script src="https://tags.example.com/t.js"></script>',
            'body_enabled' => true, 'body' => '<script>window.chat = 1;</script>',
            'on_checkout' => false, 'hosts' => ['https://tags.example.com'],
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $response = $this->get('/')->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+" src="https:\/\/tags\.example\.com\/t\.js">/', $html);
        $this->assertStringContainsString('window.chat = 1;', $html);
        $policy = (string) ($response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only'));
        if ($policy !== '') {
            $this->assertStringContainsString('https://tags.example.com', $policy);
        }

        // Not in the admin.
        $this->assertStringNotContainsString('tags.example.com/t.js', $this->actingAs($this->owner())->get(route('admin.dashboard'))->getContent());
    }

    public function test_custom_code_stays_off_checkout_unless_allowed()
    {
        $this->enableCheckout();
        $this->actingAs($this->owner())->put(route('admin.settings.general.scripts'), [
            'head_enabled' => true, 'head' => '<script>window.tag = 1;</script>', 'body_enabled' => false, 'body' => '',
            'on_checkout' => false, 'hosts' => [],
        ]);
        auth()->logout();
        $this->addToCart($this->sellable('10.00', 5));

        $this->assertStringNotContainsString('window.tag = 1;', $this->get(route('checkout.show'))->getContent());
    }

    public function test_script_hosts_must_be_https_origins()
    {
        $this->actingAs($this->owner())->put(route('admin.settings.general.scripts'), [
            'head_enabled' => true, 'head' => '', 'body_enabled' => false, 'body' => '', 'on_checkout' => false,
            'hosts' => ['http://insecure.example.com', 'https://ok.example.com; script-src *'],
        ])->assertSessionHasErrors(['hosts.0', 'hosts.1']);
    }

    public function test_social_links_reach_the_storefront()
    {
        $this->actingAs($this->owner())->put(route('admin.settings.general.social'), ['instagram' => 'https://instagram.com/acme', 'x' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('x');
        $this->actingAs($this->owner())->put(route('admin.settings.general.social'), ['instagram' => 'https://instagram.com/acme'])->assertSessionHasNoErrors();
        auth()->logout();

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('storefront.social.instagram', 'https://instagram.com/acme'));
    }
}
