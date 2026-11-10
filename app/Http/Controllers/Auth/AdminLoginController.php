<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * The staff sign-in page. It posts to the same Fortify login as the
 * storefront; only the screen differs, and it never offers sign-up.
 */
class AdminLoginController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => false,
            'status' => $request->session()->get('status'),
        ]);
    }
}
