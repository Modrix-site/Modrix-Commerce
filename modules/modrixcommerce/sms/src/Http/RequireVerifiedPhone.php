<?php

namespace ModrixCommerce\Sms\Http;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ModrixCommerce\Sms\Models\SmsPhone;
use ModrixCommerce\Sms\SmsSettings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a newly registered customer on the code page until their mobile
 * number is confirmed. Only accounts created with a number (rows with
 * verified_at null) are held, and only while the store still requires it
 * and can send texts, so a broken provider never locks customers out.
 */
final class RequireVerifiedPhone
{
    /** Routes a held customer may still use. */
    private const ALLOWED = ['modrixcommerce.sms.*', 'logout', 'verification.*', 'password.confirm*', 'admin.*', 'install.*'];

    public function __construct(private readonly SmsSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $route = $request->route();

        // Browsing the store stays open; the account area (routes behind "auth") waits.
        if (! $user instanceof User || ! $route instanceof Route || $request->routeIs(...self::ALLOWED)
            || ! collect($route->gatherMiddleware())->contains(fn ($m) => $m === 'auth' || (is_string($m) && str_starts_with($m, 'auth:')))) {
            return $next($request);
        }

        // Registration rows have no pending_phone; a number added later from the
        // account page (pending_phone set) never blocks the account.
        $pending = SmsPhone::query()->where('user_id', $user->id)->whereNull('verified_at')->whereNull('pending_phone')->exists();

        if (! $pending || ! $this->settings->requiredAtRegistration()) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Confirm your mobile number first.'], 403);
        }

        return redirect()->route('modrixcommerce.sms.verify');
    }
}
