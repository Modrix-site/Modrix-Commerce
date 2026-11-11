<?php

namespace EcoModular\BotProtection\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InventoryProtectionService
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function checkInventoryHoarding(Request $request): array
    {
        $enabled = (bool) $this->settings->of(self::MODULE, 'prevent_inventory_hoarding', true);
        if (! $enabled) {
            return ['is_hoarding' => false, 'score' => 0, 'reason' => null];
        }

        $path = $request->path();
        if (! str_contains($path, 'cart') && ! str_contains($path, 'add-to-cart')) {
            return ['is_hoarding' => false, 'score' => 0, 'reason' => null];
        }

        $ip = $request->ip() ?? '127.0.0.1';
        $key = "bot_inventory_adds_{$ip}";
        $adds = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $adds, 60);

        if ($adds > 15) { // more than 15 add to cart operations in 60s
            return [
                'is_hoarding' => true,
                'score' => 75,
                'reason' => "Rapid automated add-to-cart operations detected ({$adds} items added in 60s)",
            ];
        }

        return ['is_hoarding' => false, 'score' => 0, 'reason' => null];
    }
}
