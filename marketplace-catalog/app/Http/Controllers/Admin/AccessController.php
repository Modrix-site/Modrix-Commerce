<?php

namespace Marketplace\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Marketplace\Models\User;

/** First-admin setup and the admin sign-in. */
class AccessController
{
    public function setup(): View|RedirectResponse
    {
        return $this->hasAdmin() ? redirect('/admin/login') : view('admin.setup');
    }

    public function createAdmin(Request $request): RedirectResponse
    {
        if ($this->hasAdmin()) {
            return redirect('/admin/login');
        }

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', Password::min(12)],
        ]);

        $admin = DB::transaction(function () use ($data) {
            // Checked again inside the transaction: only the first request wins.
            if ($this->hasAdmin()) {
                return null;
            }

            $user = User::query()->create($data);
            $user->forceFill(['is_admin' => true])->save();

            return $user;
        });

        if ($admin === null) {
            return redirect('/admin/login');
        }

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect('/admin')->with('notice', 'Admin account created.');
    }

    public function login(): View
    {
        return view('admin.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials) || ! Auth::user()->is_admin) {
            Auth::logout();

            throw ValidationException::withMessages(['email' => 'These details do not match an admin account.']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }

    private function hasAdmin(): bool
    {
        return User::query()->where('is_admin', true)->exists();
    }
}
