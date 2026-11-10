<?php

namespace EcoModular\BotProtection\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CheckoutProtectionService
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function checkCheckoutAbuse(Request $request): array
    {
        $enabled = (bool) $this->settings->of(self::MODULE, 'prevent_rapid_checkout', true);
        if (!$enabled) {
            return ['is_abuse' => false, 'score' => 0, 'reason' => null];
        }

        $ip = $request->ip() ?? '127.0.0.1';
        $minSeconds = (int) $this->settings->of(self::MODULE, 'min_checkout_seconds', 4);

        // Check if checkout initiation timestamp is provided in session or request
        if ($request->hasSession()) {
            $checkoutStartedAt = $request->session()->get('checkout_started_at');
            if ($checkoutStartedAt && $request->isMethod('POST')) {
                $duration = time() - (int) $checkoutStartedAt;
                if ($duration < $minSeconds) {
                    return [
                        'is_abuse' => true,
                        'score' => 85,
                        'reason' => "Sub-human checkout velocity detected ({$duration}s elapsed, minimum {$minSeconds}s)",
                    ];
                }
            }
        }

        // Rapid repeated checkout submissions from same IP
        $key = "bot_checkout_attempts_{$ip}";
        $attempts = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, 60);

        if ($attempts > 5) {
            return [
                'is_abuse' => true,
                'score' => 80,
                'reason' => "Rapid repeated checkout attempts ({$attempts} attempts in 60s)",
            ];
        }

        return ['is_abuse' => false, 'score' => 0, 'reason' => null];
    }
}
