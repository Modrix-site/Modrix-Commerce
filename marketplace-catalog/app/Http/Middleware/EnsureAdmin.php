<?php

namespace Marketplace\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin pages: guests sign in on the admin sign-in page, customers are refused. */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest('/admin/login');
        }

        abort_unless($user->is_admin, 403);

        return $next($request);
    }
}
