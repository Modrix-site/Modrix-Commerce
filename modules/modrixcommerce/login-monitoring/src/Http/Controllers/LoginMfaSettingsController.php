<?php

namespace EcoModular\LoginMonitoring\Http\Controllers;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\LoginMonitoring\Models\LoginAuditLog;
use EcoModular\LoginMonitoring\Models\LoginMfaConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginMfaSettingsController extends Controller
{
    public const MODULE = 'modrixcommerce/login-monitoring';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function index(Request $request): Response
    {
        $currentPolicy = (string) $this->settings->of(self::MODULE, 'mfa_enforcement', 'risk_based');

        $totalUsers = User::count();
        $mfaEnabledUsers = LoginMfaConfiguration::where('mfa_enabled', true)->count();

        $mfaConfigs = LoginMfaConfiguration::query()
            ->with('user:id,name,email')
            ->orderByDesc('last_used_at')
            ->paginate(15)
            ->through(fn ($cfg) => [
                'id' => $cfg->id,
                'user_id' => $cfg->user_id,
                'user_name' => $cfg->user?->name ?? 'Unknown',
                'user_email' => $cfg->user?->email,
                'mfa_enabled' => (bool) $cfg->mfa_enabled,
                'mfa_type' => $cfg->mfa_type,
                'phone_number' => $cfg->phone_number,
                'last_used_at' => $cfg->last_used_at?->diffForHumans() ?? 'Never',
            ]);

        return Inertia::render('module/modrixcommerce/login-monitoring/admin/mfa-settings', [
            'currentPolicy' => $currentPolicy,
            'stats' => [
                'total_users' => $totalUsers,
                'mfa_enabled_users' => $mfaEnabledUsers,
                'adoption_rate' => $totalUsers > 0 ? round(($mfaEnabledUsers / $totalUsers) * 100, 1) : 0,
            ],
            'mfaConfigs' => $mfaConfigs,
        ]);
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mfa_enforcement' => ['required', 'string', 'in:optional,risk_based,staff_only,all_users'],
        ]);

        $this->settings->set(self::MODULE, 'mfa_enforcement', $validated['mfa_enforcement']);

        LoginAuditLog::record(
            "Updated MFA enforcement policy to '{$validated['mfa_enforcement']}'",
            'settings',
            'mfa_enforcement',
            ['policy' => $validated['mfa_enforcement']]
        );

        return back()->with('success', 'MFA enforcement policy has been updated.');
    }

    public function resetUserMfa(int $userId): RedirectResponse
    {
        $cfg = LoginMfaConfiguration::where('user_id', $userId)->first();
        if ($cfg) {
            $cfg->update([
                'mfa_enabled' => false,
                'secret_key' => null,
                'recovery_codes' => null,
                'phone_number' => null,
            ]);

            LoginAuditLog::record(
                "Reset MFA authentication keys for User #{$userId}",
                'user_mfa',
                (string) $userId
            );

            return back()->with('success', "MFA configuration has been reset for User #{$userId}.");
        }

        return back()->with('error', 'No active MFA configuration found for this user.');
    }
}
