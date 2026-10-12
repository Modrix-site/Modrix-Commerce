<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin area gate: the user must hold at least one role and be active.
 * Individual pages additionally require specific permissions.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isStaff() && ! $user->isDisabled(), 403);

        return $next($request);
    }
}
