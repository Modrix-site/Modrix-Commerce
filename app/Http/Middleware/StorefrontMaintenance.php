<?php

namespace App\Http\Middleware;

use App\Core\Settings\GeneralSettings;
use App\Core\Settings\StoreSettings;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Settings → General → Maintenance: closes the storefront with a 503 page.
 * Staff and allowed IP addresses still see the store; the admin, sign-in,
 * payment webhooks and returns, health checks and assets keep working.
 */
class StorefrontMaintenance
{
    /** Paths that never close (patterns for Request::is). */
    private const OPEN = [
        'admin', 'admin/*', 'login', 'logout', 'forgot-password', 'reset-password', 'reset-password/*',
        'two-factor-challenge', 'two-factor*', 'user/*', 'settings', 'settings/*', 'email/verify*',
        'webhooks/*', 'm/*/webhook*', 'checkout/complete/*', 'checkout/orders/*/payment/return',
        'health', 'up', 'install', 'theme-assets/*', 'build/*', 'storage/*', 'robots.txt', 'favicon.ico',
    ];

    public function __construct(private readonly GeneralSettings $general) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $maintenance = $this->general->maintenance();
        } catch (Throwable) {
            return $next($request);
        }

        if (! $maintenance['enabled'] || $request->is(...self::OPEN)) {
            return $next($request);
        }

        $user = $request->user();
        if (($user instanceof User && $user->isStaff()) || in_array((string) $request->ip(), $maintenance['allowed_ips'], true)) {
            $request->attributes->set('maintenance_preview', true);

            return $next($request);
        }

        $reopens = $this->reopensAt($maintenance['reopens_at']);
        $headers = $reopens !== null && $reopens->isFuture() ? ['Retry-After' => (string) max(60, (int) now()->diffInSeconds($reopens))] : ['Retry-After' => '3600'];

        return response()->view('maintenance', [
            'store' => app(StoreSettings::class)->name(),
            'headline' => $maintenance['headline'],
            'message' => $maintenance['message'],
            'reopens' => $reopens?->isFuture() ? $reopens : null,
            'logo' => app(GeneralSettings::class)->brandingProps(app(StoreSettings::class)->name())['logo'],
        ], 503, $headers);
    }

    private function reopensAt(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d H:i', $value, app(StoreSettings::class)->timezone()) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
