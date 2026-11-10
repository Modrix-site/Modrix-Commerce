<?php

namespace EcoModular\LoginMonitoring\Http\Middleware;

use Closure;
use EcoModular\LoginMonitoring\Services\LoginTrackerService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginPrecheckMiddleware
{
    public function __construct(
        private readonly LoginTrackerService $tracker,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && ($request->is('login') || $request->is('admin/login') || $request->is('api/login'))) {
            $email = (string) $request->input('email');
            $ip = $request->ip() ?? '127.0.0.1';

            if (!empty($email) && ($lockout = $this->tracker->isLockedOut($email, $ip))) {
                $minutesRemaining = max(1, now()->diffInMinutes($lockout->locked_until));

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'error' => 'Account Temporarily Locked',
                        'message' => "Too many failed attempts. Try again in {$minutesRemaining} minutes.",
                        'locked_until' => $lockout->locked_until?->toIso8601String(),
                    ], 429);
                }

                return back()->withErrors([
                    'email' => "Account is temporarily locked out due to multiple failed login attempts. Please try again in {$minutesRemaining} minutes.",
                ]);
            }
        }

        return $next($request);
    }
}
