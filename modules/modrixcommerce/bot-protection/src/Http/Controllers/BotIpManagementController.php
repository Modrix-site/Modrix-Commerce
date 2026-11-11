<?php

namespace EcoModular\BotProtection\Http\Controllers;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Http\Controllers\Controller;
use EcoModular\BotProtection\Models\BotAllowedIp;
use EcoModular\BotProtection\Models\BotAuditLog;
use EcoModular\BotProtection\Models\BotBlockedIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class BotIpManagementController extends Controller
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'blacklist');

        $blockedIps = BotBlockedIp::query()->orderByDesc('created_at')->paginate(15, ['*'], 'blocked_page');
        $allowedIps = BotAllowedIp::query()->orderByDesc('created_at')->paginate(15, ['*'], 'allowed_page');

        $blockedCountriesStr = (string) $this->settings->of(self::MODULE, 'blocked_countries', '');
        $blockedCountries = array_filter(array_map('trim', explode(',', strtoupper($blockedCountriesStr))));

        $countriesList = [
            ['code' => 'CN', 'name' => 'China'],
            ['code' => 'RU', 'name' => 'Russia'],
            ['code' => 'KP', 'name' => 'North Korea'],
            ['code' => 'IR', 'name' => 'Iran'],
            ['code' => 'VN', 'name' => 'Vietnam'],
            ['code' => 'BR', 'name' => 'Brazil'],
            ['code' => 'IN', 'name' => 'India'],
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'NL', 'name' => 'Netherlands'],
            ['code' => 'SG', 'name' => 'Singapore'],
            ['code' => 'ID', 'name' => 'Indonesia'],
            ['code' => 'UA', 'name' => 'Ukraine'],
            ['code' => 'PK', 'name' => 'Pakistan'],
            ['code' => 'NG', 'name' => 'Nigeria'],
            ['code' => 'TR', 'name' => 'Turkey'],
        ];

        return Inertia::render('module/modrixcommerce/bot-protection/admin/ip-management', [
            'tab' => $tab,
            'blockedIps' => $blockedIps,
            'allowedIps' => $allowedIps,
            'blockedCountries' => $blockedCountries,
            'countriesList' => $countriesList,
            'blockCloudAsn' => (bool) $this->settings->of(self::MODULE, 'block_cloud_hosting_asn', false),
        ]);
    }

    public function storeBlockedIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => 'required|string',
            'reason' => 'required|string|max:255',
            'attack_type' => 'nullable|string|max:60',
            'expires_days' => 'nullable|integer|min:1|max:365',
        ]);

        $expiresAt = ! empty($validated['expires_days']) ? now()->addDays($validated['expires_days']) : null;

        BotBlockedIp::updateOrCreate(
            ['ip_address' => trim($validated['ip_address'])],
            [
                'reason' => $validated['reason'],
                'attack_type' => $validated['attack_type'] ?? 'manual_block',
                'threat_level' => 'high',
                'auto_blocked' => false,
                'expires_at' => $expiresAt,
                'created_by' => auth()->user()?->name ?? 'Admin',
            ]
        );

        Cache::forget('bot_blocked_ips_list');
        BotAuditLog::record("Added IP to Blacklist: {$validated['ip_address']}", 'bot_blocked_ip', $validated['ip_address']);

        return back()->with('success', "IP {$validated['ip_address']} added to blacklist.");
    }

    public function destroyBlockedIp(BotBlockedIp $ip): RedirectResponse
    {
        $address = $ip->ip_address;
        $ip->delete();

        Cache::forget('bot_blocked_ips_list');
        BotAuditLog::record("Removed IP from Blacklist: {$address}", 'bot_blocked_ip', $address);

        return back()->with('success', "IP {$address} removed from blacklist.");
    }

    public function storeAllowedIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => 'required|string',
            'label' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'is_verified_crawler' => 'nullable|boolean',
        ]);

        BotAllowedIp::updateOrCreate(
            ['ip_address' => trim($validated['ip_address'])],
            [
                'label' => $validated['label'],
                'description' => $validated['description'] ?? null,
                'is_verified_crawler' => (bool) ($validated['is_verified_crawler'] ?? false),
                'created_by' => auth()->user()?->name ?? 'Admin',
            ]
        );

        Cache::forget('bot_allowed_ips_list');
        BotAuditLog::record("Added IP to Whitelist: {$validated['ip_address']}", 'bot_allowed_ip', $validated['ip_address']);

        return back()->with('success', "IP {$validated['ip_address']} added to whitelist.");
    }

    public function destroyAllowedIp(BotAllowedIp $ip): RedirectResponse
    {
        $address = $ip->ip_address;
        $ip->delete();

        Cache::forget('bot_allowed_ips_list');
        BotAuditLog::record("Removed IP from Whitelist: {$address}", 'bot_allowed_ip', $address);

        return back()->with('success', "IP {$address} removed from whitelist.");
    }

    public function updateCountries(Request $request): RedirectResponse
    {
        $countries = $request->input('countries', []);
        $countriesStr = implode(',', array_filter(array_map('trim', $countries)));

        // Persist setting
        $this->settings->set(self::MODULE, 'blocked_countries', $countriesStr);
        Cache::forget('bot_blocked_countries');

        BotAuditLog::record("Updated blocked countries list: {$countriesStr}", 'settings', 'blocked_countries');

        return back()->with('success', 'Country restriction rules updated.');
    }
}
