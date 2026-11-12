<?php

namespace App\Http\Middleware;

use App\Core\Settings\GeneralSettings;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings → General → Security: when two-factor is required for staff, a
 * staff member without it is sent to their security settings to turn it on
 * before using the admin.
 */
class RequireStaffTwoFactor
{
    public function __construct(private readonly GeneralSettings $general) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->two_factor_confirmed_at === null && $this->general->security()['require_two_factor']) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'This store requires two-factor authentication for staff. Turn it on to continue.']);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
