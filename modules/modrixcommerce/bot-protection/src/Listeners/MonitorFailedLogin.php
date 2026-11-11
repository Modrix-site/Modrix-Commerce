<?php

namespace EcoModular\BotProtection\Listeners;

use App\Core\Extensions\ModuleSettingsSchema;
use EcoModular\BotProtection\Models\BotBlockedIp;
use EcoModular\BotProtection\Models\BotEvent;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MonitorFailedLogin
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function handle(Failed $event): void
    {
        $enabled = (bool) $this->settings->of(self::MODULE, 'enabled', true);
        if (! $enabled) {
            return;
        }

        $request = request();
        $ip = $request->ip() ?? '127.0.0.1';
        $maxAttempts = (int) $this->settings->of(self::MODULE, 'login_rate_limit_per_minute', 5);

        $cacheKey = "bot_failed_logins_{$ip}";
        $attempts = (int) Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $attempts, 900); // 15-minute window

        if ($attempts >= $maxAttempts) {
            // Auto-block IP temporarily for 2 hours
            BotBlockedIp::updateOrCreate(
                ['ip_address' => $ip],
                [
                    'reason' => "Brute-force / Credential Stuffing: {$attempts} failed login attempts",
                    'attack_type' => 'credential_stuffing',
                    'threat_level' => 'critical',
                    'auto_blocked' => true,
                    'expires_at' => now()->addHours(2),
                    'created_by' => 'System Automated Guard',
                ]
            );

            BotEvent::create([
                'event_uuid' => (string) Str::uuid(),
                'ip_address' => $ip,
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'request_path' => substr($request->fullUrl(), 0, 500),
                'request_method' => $request->method(),
                'bot_type' => 'credential_stuffing',
                'risk_score' => 95,
                'threat_level' => 'critical',
                'action_taken' => 'block',
                'reasons' => [
                    ['indicator' => "Brute-force attack detected ({$attempts} failed logins in 15 mins)", 'points' => 95],
                ],
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'is_resolved' => false,
            ]);
        }
    }
}
