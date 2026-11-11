<?php

namespace EcoModular\BotProtection\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ScrapingProtectionService
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function isScrapingAttempt(Request $request): array
    {
        $enabled = (bool) $this->settings->of(self::MODULE, 'anti_scraping_enabled', true);
        if (! $enabled) {
            return ['is_scraper' => false, 'score' => 0, 'reason' => null];
        }

        $path = $request->path();
        $ip = $request->ip() ?? '127.0.0.1';

        // Check honeypot trip
        if (str_contains($path, 'trap-catalog') || str_contains($path, 'bot-trap')) {
            return [
                'is_scraper' => true,
                'score' => 95,
                'reason' => 'Scraper caught by hidden honeypot trap url',
            ];
        }

        // Check rapid catalog traversal
        if (str_starts_with($path, 'catalog') || str_starts_with($path, 'products') || str_starts_with($path, 'category')) {
            $key = "bot_scraper_hits_{$ip}";
            $hits = (int) Cache::get($key, 0) + 1;
            Cache::put($key, $hits, 60);

            if ($hits > 45) { // more than 45 product/catalog page hits in 60s
                return [
                    'is_scraper' => true,
                    'score' => 70,
                    'reason' => "Aggressive catalog scraping velocity ({$hits} catalog requests/min)",
                ];
            }

            if ($hits > 25) {
                return [
                    'is_scraper' => true,
                    'score' => 35,
                    'reason' => "Elevated product scraping velocity ({$hits} catalog requests/min)",
                ];
            }
        }

        return ['is_scraper' => false, 'score' => 0, 'reason' => null];
    }
}
