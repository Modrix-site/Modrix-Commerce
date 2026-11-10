<?php

namespace EcoModular\BotProtection\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use EcoModular\BotProtection\Models\BotAllowedIp;
use EcoModular\BotProtection\Models\BotBlockedIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IpCountryControlService
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function isWhitelisted(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        $allowed = Cache::remember('bot_allowed_ips_list', 120, function () {
            return BotAllowedIp::query()->get(['ip_address', 'cidr_mask'])->toArray();
        });

        foreach ($allowed as $entry) {
            if ($this->matchIp($ip, $entry['ip_address'], $entry['cidr_mask'])) {
                return true;
            }
        }

        return false;
    }

    public function isBlacklisted(string $ip): ?BotBlockedIp
    {
        $blocked = BotBlockedIp::query()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->get();

        foreach ($blocked as $entry) {
            if ($this->matchIp($ip, $entry->ip_address, $entry->cidr_mask)) {
                $entry->increment('hit_count');
                return $entry;
            }
        }

        return null;
    }

    public function detectCountry(Request $request): ?string
    {
        $headers = [
            'cf-ipcountry',
            'x-country-code',
            'cloudfront-viewer-country',
            'x-geo-country',
            'fastly-client-country',
        ];

        foreach ($headers as $header) {
            $value = $request->header($header);
            if (!empty($value) && strlen($value) === 2 && ctype_alpha($value)) {
                return strtoupper($value);
            }
        }

        return null;
    }

    public function isCountryBlocked(?string $countryCode): bool
    {
        if (empty($countryCode)) {
            return false;
        }

        $blockedCountries = Cache::remember('bot_blocked_countries', 300, function () {
            $setting = $this->settings->of(self::MODULE, 'blocked_countries', '');
            if (empty($setting)) {
                return [];
            }
            if (is_array($setting)) {
                return array_map('strtoupper', $setting);
            }
            return array_map('trim', explode(',', strtoupper((string) $setting)));
        });

        return in_array(strtoupper($countryCode), $blockedCountries, true);
    }

    public function isCloudHostingAsn(Request $request): bool
    {
        $blockCloud = (bool) $this->settings->of(self::MODULE, 'block_cloud_hosting_asn', false);
        if (!$blockCloud) {
            return false;
        }

        $hostHeaders = [
            $request->header('x-asn-name'),
            $request->header('x-organization'),
            $request->header('cf-worker'),
        ];

        $cloudSignatures = ['amazon', 'aws', 'digitalocean', 'hetzner', 'ovh', 'linode', 'vultr', 'google cloud', 'azure', 'alibaba'];

        foreach ($hostHeaders as $hdr) {
            if (empty($hdr)) {
                continue;
            }
            $hdrLower = strtolower($hdr);
            foreach ($cloudSignatures as $sig) {
                if (str_contains($hdrLower, $sig)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function matchIp(string $ip, string $target, ?int $cidr = null): bool
    {
        if ($ip === $target) {
            return true;
        }

        if ($cidr !== null || str_contains($target, '/')) {
            if ($cidr === null) {
                [$target, $cidr] = explode('/', $target);
                $cidr = (int) $cidr;
            }

            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return false;
            }

            $ipLong = ip2long($ip);
            $targetLong = ip2long($target);
            $mask = -1 << (32 - $cidr);
            $targetMasked = $targetLong & $mask;

            return ($ipLong & $mask) === $targetMasked;
        }

        return false;
    }
}
