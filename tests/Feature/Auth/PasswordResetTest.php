<?php

namespace Tests\Feature\Auth;

use App\Core\Extensions\ModuleState;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeRepository;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('storefront/auth/forgot-password')->has('storefront.theme'));
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('storefront/auth/reset-password')->has('storefront.theme')->has('token'));

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_staff_recovery_pages_keep_the_admin_layout(): void
    {
        $this->get(route('admin.password.request'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/forgot-password')->missing('storefront')->where('loginUrl', '/admin/login'));
        $this->get(route('admin.password.reset', ['token' => 'test-token', 'email' => 'admin@example.test']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/reset-password')->missing('storefront'));
    }

    public function test_customer_recovery_uses_each_available_storefront_theme(): void
    {
        config(['ecomodular.themes.path' => base_path('themes')]);
        $themes = app(ThemeRepository::class);
        $themes->sync();
        $available = $themes->all()->filter(fn ($theme) => $theme->state !== ModuleState::Invalid);
        $this->assertNotEmpty($available);
        foreach ($available as $theme) {
            app(ThemeManager::class)->activate($theme->name);
            foreach ([['password.request', [], 'forgot-password'], ['password.reset', ['token' => 'test-token', 'email' => 'shopper@example.test'], 'reset-password']] as [$route, $params, $component]) {
                $this->get(route($route, $params))->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->component('storefront/auth/'.$component)->where('storefront.theme.name', $theme->name));
            }
        }
    }

    public function test_reset_requires_password_confirmation_and_preserves_existing_password_on_failure(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        $token = app('auth.password.broker')->createToken($user);
        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'new-password-123', 'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');
        $this->assertSame($original, $user->fresh()->password);
    }
}
