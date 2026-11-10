<?php

namespace EcoModular\BotProtection\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use EcoModular\BotProtection\Models\BotEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BotDetectionEngine
{
    public const MODULE = 'modrixcommerce/bot-protection';

    private const KNOWN_CRAWLERS = [
        'googlebot',
        'bingbot',
        'slurp',
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'facebot',
        'ia_archiver',
    ];

    private const MALICIOUS_USER_AGENTS = [
        'headlesschrome' => 90,
        'phantomjs' => 95,
        'selenium' => 95,
        'puppeteer' => 95,
        'playwright' => 95,
        'webdriver' => 95,
        'scrapy' => 90,
        'sqlmap' => 100,
        'nikto' => 100,
        'masscan' => 100,
        'nmap' => 100,
        'zgrab' => 95,
        'curl' => 45,
        'python-requests' => 60,
        'python-urllib' => 65,
        'go-http-client' => 50,
        'wget' => 50,
        'libwww-perl' => 80,
        'aiohttp' => 65,
        'postmanruntime' => 40,
    ];

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
        private readonly IpCountryControlService $ipCountry,
        private readonly RulesEngineService $rulesEngine,
        private readonly ScrapingProtectionService $scraping,
        private readonly CheckoutProtectionService $checkout,
        private readonly InventoryProtectionService $inventory,
        private readonly AnomalyLearningService $learning,
    ) {}

    public function evaluate(Request $request): array
    {
        $enabled = (bool) $this->settings->of(self::MODULE, 'enabled', true);
        if (!$enabled) {
            return [
                'score' => 0,
                'threat_level' => 'low',
                'action' => 'allow',
                'is_bot' => false,
                'bot_type' => 'human',
                'reasons' => [],
            ];
        }

        $ip = $request->ip() ?? '127.0.0.1';
        $userAgent = (string) $request->userAgent();
        $uaLower = strtolower($userAgent);
        $path = '/' . ltrim($request->path(), '/');

        // 1. Whitelist check: If IP is whitelisted, immediately allow
        if ($this->ipCountry->isWhitelisted($ip)) {
            return [
                'score' => 0,
                'threat_level' => 'low',
                'action' => 'allow',
                'is_bot' => false,
                'bot_type' => 'whitelisted_ip',
                'reasons' => [['indicator' => 'Whitelisted IP address', 'points' => 0]],
            ];
        }

        // 2. Blacklist check: If IP is blacklisted, immediately block
        if ($blocked = $this->ipCountry->isBlacklisted($ip)) {
            return [
                'score' => 100,
                'threat_level' => 'critical',
                'action' => 'block',
                'is_bot' => true,
                'bot_type' => $blocked->attack_type ?: 'blacklisted_ip',
                'reasons' => [['indicator' => "IP Blacklisted: {$blocked->reason}", 'points' => 100]],
            ];
        }

        // 3. Country block check
        $countryCode = $this->ipCountry->detectCountry($request);
        if ($countryCode && $this->ipCountry->isCountryBlocked($countryCode)) {
            return [
                'score' => 100,
                'threat_level' => 'critical',
                'action' => 'block',
                'is_bot' => true,
                'bot_type' => 'country_blocked',
                'reasons' => [['indicator' => "Geo-restriction: Country ({$countryCode}) is blocked", 'points' => 100]],
            ];
        }

        // 4. Legitimate verified crawler check (Googlebot, Bingbot, etc.)
        foreach (self::KNOWN_CRAWLERS as $crawler) {
            if (str_contains($uaLower, $crawler)) {
                return [
                    'score' => 5,
                    'threat_level' => 'low',
                    'action' => 'allow',
                    'is_bot' => true,
                    'bot_type' => 'verified_crawler',
                    'reasons' => [['indicator' => "Verified search engine crawler ({$crawler})", 'points' => 5]],
                ];
            }
        }

        // 5. Evaluate custom security rules
        if ($matchedRule = $this->rulesEngine->evaluateRules($request)) {
            $score = match ($matchedRule['action']) {
                'block' => 95,
                'challenge' => 60,
                'throttle' => 50,
                default => 25,
            };

            return [
                'score' => $score,
                'threat_level' => $score >= 80 ? 'critical' : ($score >= 50 ? 'high' : 'medium'),
                'action' => $matchedRule['action'],
                'is_bot' => true,
                'bot_type' => 'custom_rule',
                'reasons' => [['indicator' => $matchedRule['reason'], 'points' => $score]],
            ];
        }

        $score = 0;
        $reasons = [];
        $detectedTypes = [];

        // 6. User-Agent Heuristics
        if (empty($userAgent)) {
            $score += 65;
            $reasons[] = ['indicator' => 'Empty User-Agent header', 'points' => 65];
            $detectedTypes[] = 'headless_or_cli';
        } else {
            foreach (self::MALICIOUS_USER_AGENTS as $badUa => $points) {
                if (str_contains($uaLower, $badUa)) {
                    $score += $points;
                    $reasons[] = ['indicator' => "Known automated bot User-Agent signature ({$badUa})", 'points' => $points];
                    $detectedTypes[] = 'bad_user_agent';
                    break;
                }
            }
        }

        // 7. Missing standard browser headers (Headless browser check)
        $isHtmlRequest = !$request->expectsJson() && !str_starts_with($path, '/api');
        if ($isHtmlRequest) {
            if (!$request->hasHeader('accept-language')) {
                $score += 25;
                $reasons[] = ['indicator' => 'Missing standard Accept-Language header', 'points' => 25];
                $detectedTypes[] = 'missing_headers';
            }
            if (!$request->hasHeader('accept-encoding')) {
                $score += 20;
                $reasons[] = ['indicator' => 'Missing Accept-Encoding header (typical of automated scripts)', 'points' => 20];
                $detectedTypes[] = 'missing_headers';
            }
        }

        // 8. Cloud hosting / Datacenter ASN check
        if ($this->ipCountry->isCloudHostingAsn($request)) {
            $score += 40;
            $reasons[] = ['indicator' => 'Traffic originating from commercial cloud hosting datacenter (AWS/DO/Hetzner/OVH)', 'points' => 40];
            $detectedTypes[] = 'cloud_hosting_asn';
        }

        // 9. Scraping heuristics
        $scrapingCheck = $this->scraping->isScrapingAttempt($request);
        if ($scrapingCheck['is_scraper']) {
            $score += $scrapingCheck['score'];
            $reasons[] = ['indicator' => $scrapingCheck['reason'], 'points' => $scrapingCheck['score']];
            $detectedTypes[] = 'scraper';
        }

        // 10. Checkout abuse heuristics
        if (str_contains($path, 'checkout')) {
            $checkoutCheck = $this->checkout->checkCheckoutAbuse($request);
            if ($checkoutCheck['is_abuse']) {
                $score += $checkoutCheck['score'];
                $reasons[] = ['indicator' => $checkoutCheck['reason'], 'points' => $checkoutCheck['score']];
                $detectedTypes[] = 'checkout_bot';
            }
        }

        // 11. Inventory hoarding heuristics
        $inventoryCheck = $this->inventory->checkInventoryHoarding($request);
        if ($inventoryCheck['is_hoarding']) {
            $score += $inventoryCheck['score'];
            $reasons[] = ['indicator' => $inventoryCheck['reason'], 'points' => $inventoryCheck['score']];
            $detectedTypes[] = 'inventory_hoarder';
        }

        // 12. Request velocity / Rate limiting
        $rateLimit = (int) $this->settings->of(self::MODULE, 'rate_limit_per_minute', 120);
        $rateKey = "bot_rate_count_{$ip}";
        $currentMinuteHits = (int) Cache::get($rateKey, 0) + 1;
        Cache::put($rateKey, $currentMinuteHits, 60);

        if ($currentMinuteHits > $rateLimit) {
            $excess = $currentMinuteHits - $rateLimit;
            $ratePoints = min(50, 20 + ($excess * 2));
            $score += $ratePoints;
            $reasons[] = ['indicator' => "Rate limit exceeded: {$currentMinuteHits} reqs/min (threshold {$rateLimit})", 'points' => $ratePoints];
            $detectedTypes[] = 'rate_limit_exceeded';
        }

        // 13. Anomaly / Adaptive Learning historical modifier
        $adaptiveModifier = $this->learning->getAdaptiveRiskModifier($ip);
        if ($adaptiveModifier > 0) {
            $score += $adaptiveModifier;
            $reasons[] = ['indicator' => "Historical threat penalty: IP has {$adaptiveModifier} past violation penalty points", 'points' => $adaptiveModifier];
        }

        // 14. Sensitivity Level adjustments
        $sensitivity = (string) $this->settings->of(self::MODULE, 'sensitivity_level', 'medium');
        $multiplier = match ($sensitivity) {
            'low' => 0.85,
            'high' => 1.2,
            'paranoid' => 1.4,
            default => 1.0,
        };
        $finalScore = min(100, max(0, (int) round($score * $multiplier)));

        // Determine Threat Level & Action
        $challengeThreshold = (int) $this->settings->of(self::MODULE, 'challenge_threshold', 45);
        $blockThreshold = (int) $this->settings->of(self::MODULE, 'block_threshold', 80);

        $action = 'allow';
        $threatLevel = 'low';

        if ($finalScore >= $blockThreshold) {
            $action = 'block';
            $threatLevel = 'critical';
        } elseif ($finalScore >= $challengeThreshold) {
            // Check if visitor has already verified recently
            if ($request->hasSession() && $request->session()->get('bot_protection_verified')) {
                $action = 'allow';
                $threatLevel = 'medium';
            } else {
                $action = 'challenge';
                $threatLevel = 'high';
            }
        } elseif ($finalScore >= 30) {
            $action = 'monitor';
            $threatLevel = 'medium';
        }

        // Primary bot classification
        $botType = !empty($detectedTypes) ? $detectedTypes[0] : ($finalScore >= 30 ? 'suspected_bot' : 'human');
        $isBot = $finalScore >= 30;

        // Record learning profile
        if ($finalScore > 10) {
            $this->learning->recordRequestAndViolation($ip, $finalScore, $countryCode, $reasons[0] ?? null);
        }

        // Record BotEvent if action is challenge or block or score >= 50
        if ($finalScore >= 40 || in_array($action, ['challenge', 'block', 'throttle'], true)) {
            $this->recordBotEvent($request, $finalScore, $threatLevel, $action, $botType, $reasons, $countryCode);
        }

        return [
            'score' => $finalScore,
            'threat_level' => $threatLevel,
            'action' => $action,
            'is_bot' => $isBot,
            'bot_type' => $botType,
            'reasons' => $reasons,
        ];
    }

    private function recordBotEvent(Request $request, int $score, string $threatLevel, string $action, string $botType, array $reasons, ?string $countryCode): void
    {
        try {
            BotEvent::create([
                'event_uuid' => (string) Str::uuid(),
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'request_path' => substr($request->fullUrl(), 0, 500),
                'request_method' => $request->method(),
                'bot_type' => $botType,
                'risk_score' => $score,
                'threat_level' => $threatLevel,
                'action_taken' => $action,
                'reasons' => $reasons,
                'request_headers' => array_intersect_key($request->headers->all(), array_flip(['host', 'user-agent', 'accept', 'accept-language', 'sec-ch-ua'])),
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'user_id' => auth()->id(),
                'country_code' => $countryCode,
                'city' => null,
                'asn' => $request->header('x-asn-name'),
                'is_resolved' => false,
            ]);
        } catch (\Throwable) {
            // Ignore DB errors during live evaluation to prevent disrupting requests
        }
    }
}
