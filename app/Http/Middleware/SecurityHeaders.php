<?php

namespace App\Http\Middleware;

use App\Core\Extensions\ModuleContentSecurity;
use App\Core\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers for every response.
 *
 * The Content-Security-Policy allows scripts only from this origin or with
 * the per-request nonce (Vite tags and the inline appearance script carry
 * it). Styles allow inline values because theme tokens and component styles
 * are rendered inline; theme values are validated before they reach CSS
 * (ADR-031). Responses that set their own policy (theme assets) keep it.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('ecomodular.security.csp', 'enforce');
        $csp = $mode !== 'off' && ! Vite::isRunningHot();

        if ($csp) {
            Vite::useCspNonce();
        }

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        // The theme editor frames the storefront preview from the same origin.
        $framed = $request->attributes->get('theme.editor') === true;
        $headers->set('X-Frame-Options', $framed ? 'SAMEORIGIN' : 'DENY', false);
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()', false);
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin', false);

        if ($request->isSecure() && config('ecomodular.security.hsts')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        $hasPolicy = $headers->has('Content-Security-Policy') || $headers->has('Content-Security-Policy-Report-Only');

        if ($csp && ! $hasPolicy) {
            $name = $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $headers->set($name, $this->policy(Vite::cspNonce(), $framed));
        }

        return $response;
    }

    private function policy(?string $nonce, bool $framed = false): string
    {
        // Origins declared by enabled modules (manifest content_security, ADR-047).
        $modules = app(ModuleContentSecurity::class);
        $directive = fn (string $name, array $base) => trim($name.' '.implode(' ', array_values(array_unique([...$base, ...$modules->sources($name)]))));
        $script = $nonce === null ? ["'self'"] : ["'self'", "'nonce-{$nonce}'"];
        $frames = $modules->sources('frame-src');
        // Hosts allowed for Settings → General → Custom code.
        $custom = rescue(fn () => app(GeneralSettings::class)->scriptHosts(), [], false);

        return implode('; ', array_filter([
            "default-src 'self'",
            $directive('script-src', [...$script, ...$custom]),
            "style-src 'self' 'unsafe-inline'",
            $directive('img-src', ["'self'", 'data:', 'blob:', ...$this->mediaOrigins(), ...$custom]),
            "font-src 'self' data:",
            $directive('connect-src', ["'self'", ...$custom]),
            $frames === [] && $custom === [] ? null : $directive('frame-src', ["'self'", ...$custom]),
            "object-src 'none'",
            "base-uri 'self'",
            $framed ? "frame-ancestors 'self'" : "frame-ancestors 'none'",
            // Hosted payment pages are reached by full-page redirects.
            $directive('form-action', ["'self'", 'https://checkout.stripe.com']),
        ]));
    }

    /**
     * Origins images may come from: the app URL, the media disk (a CDN or
     * object storage in production) and any configured extra sources.
     *
     * @return list<string>
     */
    private function mediaOrigins(): array
    {
        $disk = (string) config('ecomodular.media_disk', 'public');
        $urls = [config('app.url'), config("filesystems.disks.{$disk}.url"), ...(array) config('ecomodular.security.img_sources', [])];
        $origins = [];

        foreach ($urls as $url) {
            $parts = is_string($url) ? parse_url($url) : false;

            if (is_array($parts) && isset($parts['scheme'], $parts['host']) && in_array($parts['scheme'], ['http', 'https'], true)) {
                $origins[] = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }

        return array_values(array_unique($origins));
    }
}
