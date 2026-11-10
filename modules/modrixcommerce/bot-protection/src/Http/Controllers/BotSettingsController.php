<?php

namespace EcoModular\BotProtection\Http\Controllers;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Http\Controllers\Controller;
use EcoModular\BotProtection\Models\BotAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotSettingsController extends Controller
{
    public const MODULE = 'modrixcommerce/bot-protection';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function index(): Response
    {
        $currentSettings = [
            'enabled' => (bool) $this->settings->of(self::MODULE, 'enabled', true),
            'sensitivity_level' => (string) $this->settings->of(self::MODULE, 'sensitivity_level', 'medium'),
            'challenge_threshold' => (int) $this->settings->of(self::MODULE, 'challenge_threshold', 45),
            'block_threshold' => (int) $this->settings->of(self::MODULE, 'block_threshold', 80),
            'captcha_provider' => (string) $this->settings->of(self::MODULE, 'captcha_provider', 'custom_interactive'),
            'captcha_site_key' => (string) $this->settings->of(self::MODULE, 'captcha_site_key', ''),
            'captcha_secret_key' => (string) $this->settings->of(self::MODULE, 'captcha_secret_key', ''),
            'rate_limit_per_minute' => (int) $this->settings->of(self::MODULE, 'rate_limit_per_minute', 120),
            'api_rate_limit_per_minute' => (int) $this->settings->of(self::MODULE, 'api_rate_limit_per_minute', 60),
            'login_rate_limit_per_minute' => (int) $this->settings->of(self::MODULE, 'login_rate_limit_per_minute', 5),
            'prevent_rapid_checkout' => (bool) $this->settings->of(self::MODULE, 'prevent_rapid_checkout', true),
            'min_checkout_seconds' => (int) $this->settings->of(self::MODULE, 'min_checkout_seconds', 4),
            'prevent_inventory_hoarding' => (bool) $this->settings->of(self::MODULE, 'prevent_inventory_hoarding', true),
            'anti_scraping_enabled' => (bool) $this->settings->of(self::MODULE, 'anti_scraping_enabled', true),
            'block_cloud_hosting_asn' => (bool) $this->settings->of(self::MODULE, 'block_cloud_hosting_asn', false),
            'email_alerts_enabled' => (bool) $this->settings->of(self::MODULE, 'email_alerts_enabled', true),
            'alert_emails' => (string) $this->settings->of(self::MODULE, 'alert_emails', ''),
            'slack_webhook_url' => (string) $this->settings->of(self::MODULE, 'slack_webhook_url', ''),
            'custom_webhook_url' => (string) $this->settings->of(self::MODULE, 'custom_webhook_url', ''),
        ];

        return Inertia::render('module/modrixcommerce/bot-protection/admin/settings', [
            'settings' => $currentSettings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $keys = [
            'enabled', 'sensitivity_level', 'challenge_threshold', 'block_threshold',
            'captcha_provider', 'captcha_site_key', 'captcha_secret_key',
            'rate_limit_per_minute', 'api_rate_limit_per_minute', 'login_rate_limit_per_minute',
            'prevent_rapid_checkout', 'min_checkout_seconds', 'prevent_inventory_hoarding',
            'anti_scraping_enabled', 'block_cloud_hosting_asn',
            'email_alerts_enabled', 'alert_emails', 'slack_webhook_url', 'custom_webhook_url',
        ];

        $oldValues = [];
        $newValues = [];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                $oldValues[$key] = $this->settings->of(self::MODULE, $key);
                $val = $request->input($key);
                $this->settings->set(self::MODULE, $key, $val);
                $newValues[$key] = $val;
            }
        }

        BotAuditLog::record('Updated Bot Protection Settings', 'settings', null, $oldValues, $newValues);

        return back()->with('success', 'Bot protection settings updated successfully.');
    }
}
