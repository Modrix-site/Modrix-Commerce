<?php

namespace EcoModular\GdprCookieConsent\Http;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Http\Controllers\Controller;
use EcoModular\GdprCookieConsent\GdprCookieConsentServiceProvider;
use EcoModular\GdprCookieConsent\Models\ConsentRecord;
use EcoModular\GdprCookieConsent\Support\CountryDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Storefront: record a consent decision from the banner. The choice is stored
 * for accountability and mirrored back so the client can set its cookie. No
 * IP address is stored; a random token links decisions from one browser.
 */
class ConsentController extends Controller
{
    private const DECISIONS = ['accept', 'reject', 'customize'];

    public function store(Request $request, ModuleSettingsSchema $settings): JsonResponse
    {
        if (! (bool) $settings->of(GdprCookieConsentServiceProvider::MODULE, 'enabled', true)) {
            return response()->json(['ok' => false], 423);
        }

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:'.implode(',', self::DECISIONS)],
            'analytics' => ['sometimes', 'boolean'],
            'marketing' => ['sometimes', 'boolean'],
            'page' => ['nullable', 'string', 'max:191'],
            'token' => ['nullable', 'string', 'max:64'],
        ]);

        $token = $this->token($request, $data);

        $analytics = $data['decision'] === 'accept' || (bool) ($data['analytics'] ?? false);
        $marketing = $data['decision'] === 'accept' || (bool) ($data['marketing'] ?? false);

        ConsentRecord::forceCreate([
            'visitor_token' => $token,
            'decision' => $data['decision'],
            'analytics_allowed' => $analytics,
            'marketing_allowed' => $marketing,
            'country_code' => CountryDetector::detect($request),
            'consent_text' => (string) $settings->of(GdprCookieConsentServiceProvider::MODULE, 'body', ''),
            'page' => $this->page($request, $data),
            'user_agent' => Str::limit((string) $request->userAgent(), 191, ''),
            'decided_at' => now(),
        ]);

        $expiryDays = max(1, min(730, (int) $settings->of(GdprCookieConsentServiceProvider::MODULE, 'consent_expiry_days', 365)));

        return response()->json([
            'ok' => true,
            'token' => $token,
            'analytics' => $analytics,
            'marketing' => $marketing,
            'expiry_days' => $expiryDays,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function token(Request $request, array $data): string
    {
        $token = $data['token'] ?? null;

        if (is_string($token) && preg_match('/^[A-Za-z0-9]{16,64}$/', $token)) {
            return $token;
        }

        $cookie = $request->cookie('gdpr_consent_token');

        return is_string($cookie) && preg_match('/^[A-Za-z0-9]{16,64}$/', $cookie) ? $cookie : Str::random(40);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(Request $request, array $data): string
    {
        $page = $data['page'] ?? null;

        if (! is_string($page) || $page === '') {
            return '/';
        }

        return mb_substr('/'.ltrim(explode('?', $page)[0], '/'), 0, 191);
    }
}
