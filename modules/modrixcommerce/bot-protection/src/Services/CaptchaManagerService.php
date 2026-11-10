<?php

namespace EcoModular\BotProtection\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use EcoModular\BotProtection\Models\BotCaptchaChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CaptchaManagerService
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function createChallenge(Request $request, ?string $targetUrl = null): BotCaptchaChallenge
    {
        $provider = (string) $this->settings->of(self::MODULE, 'captcha_provider', 'custom_interactive');

        return BotCaptchaChallenge::create([
            'challenge_token' => Str::random(48),
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'captcha_type' => $provider,
            'status' => 'pending',
            'target_url' => $targetUrl ?? $request->fullUrl(),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function verifyChallenge(Request $request, string $token, array $payload): bool
    {
        $challenge = BotCaptchaChallenge::query()
            ->where('challenge_token', $token)
            ->where('status', 'pending')
            ->first();

        if (!$challenge || $challenge->isExpired()) {
            return false;
        }

        $challenge->increment('attempts');
        $provider = $challenge->captcha_type;
        $passed = false;

        switch ($provider) {
            case 'turnstile':
                $passed = $this->verifyTurnstile($payload['cf_turnstile_response'] ?? '', $request->ip());
                break;
            case 'recaptcha_v2':
            case 'recaptcha_v3':
                $passed = $this->verifyGoogleRecaptcha($payload['g_recaptcha_response'] ?? '', $request->ip());
                break;
            case 'hcaptcha':
                $passed = $this->verifyHCaptcha($payload['h_captcha_response'] ?? '', $request->ip());
                break;
            case 'custom_interactive':
            default:
                $passed = $this->verifyCustomInteractive($payload, $challenge);
                break;
        }

        if ($passed) {
            $challenge->update([
                'status' => 'solved',
                'solved_at' => now(),
            ]);

            // Set bypass in session or cookie
            if ($request->hasSession()) {
                $request->session()->put('bot_protection_verified', true);
                $request->session()->put('bot_protection_verified_at', now()->timestamp);
            }

            return true;
        }

        if ($challenge->attempts >= 5) {
            $challenge->update(['status' => 'failed']);
        }

        return false;
    }

    private function verifyCustomInteractive(array $payload, BotCaptchaChallenge $challenge): bool
    {
        // Custom interactive verification validates proof-of-work puzzle:
        // puzzle_ans must match expected hash or math solution
        $solution = $payload['solution'] ?? null;
        $timestamp = (int) ($payload['timestamp'] ?? 0);

        if (!$solution || abs(time() - $timestamp) > 900) {
            return false;
        }

        $expectedHash = hash_hmac('sha256', "{$challenge->challenge_token}:{$timestamp}:{$challenge->ip_address}", config('app.key'));
        return hash_equals($expectedHash, (string) $solution);
    }

    private function verifyTurnstile(string $responseToken, ?string $remoteIp): bool
    {
        if (empty($responseToken)) {
            return false;
        }

        $secret = (string) $this->settings->of(self::MODULE, 'captcha_secret_key', '');
        if (empty($secret)) {
            return true; // Test mode if no key configured
        }

        try {
            $resp = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $responseToken,
                'remoteip' => $remoteIp,
            ]);

            return (bool) ($resp->json('success') ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    private function verifyGoogleRecaptcha(string $responseToken, ?string $remoteIp): bool
    {
        if (empty($responseToken)) {
            return false;
        }

        $secret = (string) $this->settings->of(self::MODULE, 'captcha_secret_key', '');
        if (empty($secret)) {
            return true;
        }

        try {
            $resp = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $responseToken,
                'remoteip' => $remoteIp,
            ]);

            return (bool) ($resp->json('success') ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    private function verifyHCaptcha(string $responseToken, ?string $remoteIp): bool
    {
        if (empty($responseToken)) {
            return false;
        }

        $secret = (string) $this->settings->of(self::MODULE, 'captcha_secret_key', '');
        if (empty($secret)) {
            return true;
        }

        try {
            $resp = Http::asForm()->post('https://hcaptcha.com/siteverify', [
                'secret' => $secret,
                'response' => $responseToken,
                'remoteip' => $remoteIp,
            ]);

            return (bool) ($resp->json('success') ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function generateClientChallengeToken(BotCaptchaChallenge $challenge): array
    {
        $timestamp = time();
        $expectedSolution = hash_hmac('sha256', "{$challenge->challenge_token}:{$timestamp}:{$challenge->ip_address}", config('app.key'));

        return [
            'token' => $challenge->challenge_token,
            'timestamp' => $timestamp,
            'expected_solution' => $expectedSolution,
            'type' => $challenge->captcha_type,
            'site_key' => (string) $this->settings->of(self::MODULE, 'captcha_site_key', ''),
        ];
    }
}
