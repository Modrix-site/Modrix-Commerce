<?php

namespace EcoModular\BotProtection\Listeners;

use App\Core\Extensions\ModuleSettingsSchema;
use EcoModular\BotProtection\Models\BotEvent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MonitorUserRegistration
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function handle(Registered $event): void
    {
        $enabled = (bool) $this->settings->of(self::MODULE, 'enabled', true);
        if (!$enabled) {
            return;
        }

        $request = request();
        $ip = $request->ip() ?? '127.0.0.1';

        // Check registration velocity per IP
        $cacheKey = "bot_registrations_{$ip}";
        $count = (int) Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $count, 3600); // 1 hour window

        if ($count > 3) { // more than 3 account creations from same IP within 1 hour
            BotEvent::create([
                'event_uuid' => (string) Str::uuid(),
                'ip_address' => $ip,
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'request_path' => substr($request->fullUrl(), 0, 500),
                'request_method' => $request->method(),
                'bot_type' => 'fake_registration',
                'risk_score' => 75,
                'threat_level' => 'high',
                'action_taken' => 'monitor',
                'reasons' => [
                    ['indicator' => "Burst account creation detected ({$count} registrations from IP in 1h)", 'points' => 75],
                ],
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'is_resolved' => false,
            ]);
        }
    }
}
