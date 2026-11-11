<?php

namespace EcoModular\LoginMonitoring\Http\Controllers;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Http\Controllers\Controller;
use EcoModular\LoginMonitoring\Models\LoginAllowedIp;
use EcoModular\LoginMonitoring\Models\LoginAuditLog;
use EcoModular\LoginMonitoring\Models\LoginBlockedIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginPoliciesController extends Controller
{
    public const MODULE = 'modrixcommerce/login-monitoring';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function index(): Response
    {
        $policySettings = [
            'enabled' => (bool) $this->settings->of(self::MODULE, 'enabled', true),
            'max_failed_attempts' => (int) $this->settings->of(self::MODULE, 'max_failed_attempts', 5),
            'lockout_duration_minutes' => (int) $this->settings->of(self::MODULE, 'lockout_duration_minutes', 30),
            'session_lifetime_hours' => (int) $this->settings->of(self::MODULE, 'session_lifetime_hours', 24),
            'detect_impossible_travel' => (bool) $this->settings->of(self::MODULE, 'detect_impossible_travel', true),
            'impossible_travel_speed_kmh' => (int) $this->settings->of(self::MODULE, 'impossible_travel_speed_kmh', 800),
            'alert_on_new_device' => (bool) $this->settings->of(self::MODULE, 'alert_on_new_device', true),
            'alert_on_suspicious_login' => (bool) $this->settings->of(self::MODULE, 'alert_on_suspicious_login', true),
            'email_alerts_enabled' => (bool) $this->settings->of(self::MODULE, 'email_alerts_enabled', true),
            'alert_emails' => (string) $this->settings->of(self::MODULE, 'alert_emails', ''),
            'slack_webhook_url' => (string) $this->settings->of(self::MODULE, 'slack_webhook_url', ''),
        ];

        $allowedIps = LoginAllowedIp::orderByDesc('created_at')->get();
        $blockedIps = LoginBlockedIp::orderByDesc('created_at')->get();

        return Inertia::render('module/modrixcommerce/login-monitoring/admin/policies', [
            'settings' => $policySettings,
            'allowedIps' => $allowedIps,
            'blockedIps' => $blockedIps,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'max_failed_attempts' => ['required', 'integer', 'min:2', 'max:20'],
            'lockout_duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'session_lifetime_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'detect_impossible_travel' => ['required', 'boolean'],
            'impossible_travel_speed_kmh' => ['required', 'integer', 'min:300', 'max:1500'],
            'alert_on_new_device' => ['required', 'boolean'],
            'alert_on_suspicious_login' => ['required', 'boolean'],
            'email_alerts_enabled' => ['required', 'boolean'],
            'alert_emails' => ['nullable', 'string', 'max:500'],
            'slack_webhook_url' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated as $key => $val) {
            $this->settings->set(self::MODULE, $key, $val);
        }

        LoginAuditLog::record(
            'Updated login monitoring security & lockout policies',
            'settings',
            'policies',
            $validated
        );

        return back()->with('success', 'Login security policies and threshold settings saved.');
    }

    public function storeAllowedIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:45'],
            'label' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        LoginAllowedIp::create([
            'ip_address' => $validated['ip_address'],
            'label' => $validated['label'] ?? 'Corporate / Office IP',
            'description' => $validated['description'] ?? null,
            'created_by' => auth()->user()?->name ?? 'Admin',
        ]);

        LoginAuditLog::record("Added IP to allowlist: {$validated['ip_address']}", 'allowed_ip', $validated['ip_address']);

        return back()->with('success', "IP {$validated['ip_address']} has been added to allowed list.");
    }

    public function destroyAllowedIp(int $id): RedirectResponse
    {
        $ip = LoginAllowedIp::findOrFail($id);
        $addr = $ip->ip_address;
        $ip->delete();

        LoginAuditLog::record("Removed IP from allowlist: {$addr}", 'allowed_ip', $addr);

        return back()->with('success', "IP {$addr} removed from allowed list.");
    }

    public function storeBlockedIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:45'],
            'reason' => ['nullable', 'string', 'max:255'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        $expiresAt = ! empty($validated['duration_hours']) ? now()->addHours($validated['duration_hours']) : null;

        LoginBlockedIp::create([
            'ip_address' => $validated['ip_address'],
            'reason' => $validated['reason'] ?? 'Manually blocked by administrator',
            'attack_type' => 'manual',
            'auto_blocked' => false,
            'expires_at' => $expiresAt,
            'created_by' => auth()->user()?->name ?? 'Admin',
        ]);

        LoginAuditLog::record("Added IP to blocklist: {$validated['ip_address']}", 'blocked_ip', $validated['ip_address']);

        return back()->with('success', "IP {$validated['ip_address']} has been added to blocked IPs.");
    }

    public function destroyBlockedIp(int $id): RedirectResponse
    {
        $ip = LoginBlockedIp::findOrFail($id);
        $addr = $ip->ip_address;
        $ip->delete();

        LoginAuditLog::record("Removed IP from blocklist: {$addr}", 'blocked_ip', $addr);

        return back()->with('success', "IP {$addr} unblocked.");
    }
}
