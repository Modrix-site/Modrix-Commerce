<?php

namespace EcoModular\BotProtection\Http\Middleware;

use Closure;
use EcoModular\BotProtection\Services\BotDetectionEngine;
use EcoModular\BotProtection\Services\CaptchaManagerService;
use EcoModular\BotProtection\Services\TrafficMonitoringService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BotProtectionMiddleware
{
    public function __construct(
        private readonly BotDetectionEngine $engine,
        private readonly TrafficMonitoringService $traffic,
        private readonly CaptchaManagerService $captcha,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Exempt internal paths, assets, and bot-protection verification routes
        if ($this->shouldExempt($request)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $evaluation = $this->engine->evaluate($request);
        $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        // 1. Action: BLOCK
        if ($evaluation['action'] === 'block') {
            $this->traffic->logRequest($request, $evaluation, 403, $responseTimeMs);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Access Denied',
                    'message' => 'Your request was blocked by Bot Protection due to automated bot activity.',
                    'risk_score' => $evaluation['score'],
                    'threat_level' => $evaluation['threat_level'],
                ], 403);
            }

            return response()->view('bot-protection::blocked', [
                'ip' => $request->ip(),
                'score' => $evaluation['score'],
                'reasons' => $evaluation['reasons'],
                'ray_id' => bin2hex(random_bytes(8)),
            ], 403);
        }

        // 2. Action: CHALLENGE
        if ($evaluation['action'] === 'challenge') {
            $this->traffic->logRequest($request, $evaluation, 403, $responseTimeMs);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Challenge Required',
                    'message' => 'Interactive verification is required to access this resource.',
                    'risk_score' => $evaluation['score'],
                    'challenge_url' => url('/m/modrixcommerce/bot-protection/challenge?return_to=' . urlencode($request->fullUrl())),
                ], 403);
            }

            $challenge = $this->captcha->createChallenge($request, $request->fullUrl());
            return redirect()->to(url('/m/modrixcommerce/bot-protection/challenge/' . $challenge->challenge_token));
        }

        // 3. Action: THROTTLE
        if ($evaluation['action'] === 'throttle') {
            $this->traffic->logRequest($request, $evaluation, 429, $responseTimeMs);

            return response()->json([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please slow down your requests.',
            ], 429, ['Retry-After' => '30']);
        }

        // 4. Action: ALLOW / MONITOR
        $response = $next($request);
        $totalTime = (int) round((microtime(true) - $startTime) * 1000);
        $this->traffic->logRequest($request, $evaluation, $response->getStatusCode(), $totalTime);

        $response->headers->set('X-Bot-Score', (string) $evaluation['score']);
        return $response;
    }

    private function shouldExempt(Request $request): bool
    {
        $path = $request->path();

        // Exempt assets, internal vite build files, and debug endpoints
        if (str_starts_with($path, 'build/') ||
            str_starts_with($path, 'theme-assets/') ||
            str_starts_with($path, 'vendor/') ||
            str_starts_with($path, 'favicon.ico') ||
            str_starts_with($path, 'health') ||
            str_starts_with($path, 'up') ||
            str_starts_with($path, 'm/modrixcommerce/bot-protection/challenge') ||
            str_starts_with($path, 'm/modrixcommerce/bot-protection/verify')
        ) {
            return true;
        }

        // Exempt staff users in admin area
        if ($request->is('admin*') && auth()->check() && (auth()->user()->isStaff() ?? false)) {
            return true;
        }

        return false;
    }
}
