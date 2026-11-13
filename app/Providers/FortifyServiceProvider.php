<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\GeneralSettings;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // Disabled accounts cannot authenticate. The check runs after the
        // password comparison so response timing does not reveal account status.
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::query()->where('email', Str::lower((string) $request->input(Fortify::username())))->first();

            if ($user && Hash::check((string) $request->input('password'), $user->password) && ! $user->isDisabled()) {
                return $user;
            }

            return null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('storefront/auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => rescue(fn () => app(GeneralSettings::class)->registrationEnabled(), true, false),
            'status' => $request->session()->get('status'),
            // Why the shopper was sent here, e.g. 'checkout' when the store requires an account.
            'reason' => $request->session()->get('login_reason'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render($request->routeIs('admin.*') ? 'auth/reset-password' : 'storefront/auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render($request->routeIs('admin.*') ? 'auth/forgot-password' : 'storefront/auth/forgot-password', [
            'status' => $request->session()->get('status'),
            'loginUrl' => $request->routeIs('admin.*') ? route('admin.login', absolute: false) : route('login', absolute: false),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('storefront/auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'registerSlots' => app(StorefrontSlots::class)->render('auth.register', []),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn (Request $request) => Inertia::render($request->routeIs('account.*') ? 'storefront/auth/confirm-password' : 'auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            // Settings → General → Security: sign-in attempts per minute.
            $attempts = rescue(fn () => app(GeneralSettings::class)->security()['login_attempts'], 5, false);

            return Limit::perMinute($attempts)->by($throttleKey);
        });

        // Password reset, reset submission and registration: per IP, on top of the broker's per-email throttle.
        RateLimiter::for('auth-sensitive', fn (Request $request) => Limit::perMinute(5)->by('auth-sensitive:'.$request->ip()));

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
