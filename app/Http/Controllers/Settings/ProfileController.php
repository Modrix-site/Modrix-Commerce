<?php

namespace App\Http\Controllers\Settings;

use App\Core\Authorization\EscalationGuard;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\MarketplaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, EscalationGuard $guard): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $guard) {
            // A store must always keep an owner: transfer ownership first.
            $guard->ensureNotLastOwner($user, 'password', 'You are the last owner of this store. Make another user an owner before deleting your account.');

            // Marketplace licenses and orders are kept for the buyer and for accounting.
            if (Schema::hasTable('licenses') && (License::query()->where('user_id', $user->id)->exists() || MarketplaceOrder::query()->where('user_id', $user->id)->exists())) {
                throw ValidationException::withMessages(['password' => 'This account holds marketplace licenses or orders. Contact the store to close it.']);
            }

            // Log out first: logging out saves the user (remember token), which would re-create a deleted row.
            Auth::logout();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
