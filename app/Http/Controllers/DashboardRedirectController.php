<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Post-login landing: staff go to the admin dashboard, customers to their account.
 */
class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isStaff()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('account.show');
    }
}
