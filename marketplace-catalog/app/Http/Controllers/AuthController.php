<?php

namespace Marketplace\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Marketplace\Models\User;

/** Customer and publisher sign-in, registration and sign-out. */
class AuthController
{
    public function login(Request $request): View
    {
        return view('auth.login', ['next' => self::next($request)]);
    }

    public function register(Request $request): View
    {
        return view('auth.register', ['next' => self::next($request)]);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }

        $request->session()->regenerate();

        return redirect(self::next($request));
    }

    public function store(Request $request): RedirectResponse
    {
        // Emails are stored lowercase, so sign-in and uniqueness ignore case on every database.
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        Auth::login(User::query()->create($data));
        $request->session()->regenerate();

        return redirect(self::next($request))->with('notice', 'Your account is ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /** A same-site path to return to after signing in (never another host). */
    public static function next(Request $request, string $default = '/account'): string
    {
        $next = (string) $request->input('next', '');

        return str_starts_with($next, '/') && ! str_starts_with($next, '//') && ! str_contains($next, '\\') ? $next : $default;
    }
}
