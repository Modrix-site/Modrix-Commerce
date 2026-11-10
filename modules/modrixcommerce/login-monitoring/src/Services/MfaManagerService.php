<?php

namespace EcoModular\LoginMonitoring\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Models\User;
use EcoModular\LoginMonitoring\Models\LoginMfaConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class MfaManagerService
{
    public const MODULE = 'modrixcommerce/login-monitoring';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function isMfaRequired(User $user, int $riskScore): bool
    {
        $policy = (string) $this->settings->of(self::MODULE, 'mfa_enforcement', 'risk_based');

        if ($policy === 'all_users') {
            return true;
        }

        if ($policy === 'staff_only') {
            return (bool) ($user->isStaff() ?? false);
        }

        if ($policy === 'risk_based') {
            return $riskScore >= 40;
        }

        // Check if user has voluntarily enabled MFA
        $cfg = LoginMfaConfiguration::query()->where('user_id', $user->id)->first();
        return $cfg ? (bool) $cfg->mfa_enabled : false;
    }

    public function sendEmailOtp(User $user): string
    {
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = "login_mfa_otp_{$user->id}";
        Cache::put($cacheKey, $otp, 300); // 5 mins

        try {
            Mail::raw("Your security verification code is: {$otp}\n\nThis code will expire in 5 minutes.", function ($msg) use ($user) {
                $msg->to($user->email)->subject('Your Verification Code');
            });
        } catch (\Throwable) {
            // Silently log or continue
        }

        return $otp;
    }

    public function verifyEmailOtp(User $user, string $code): bool
    {
        $cacheKey = "login_mfa_otp_{$user->id}";
        $expected = Cache::get($cacheKey);

        if ($expected && hash_equals((string) $expected, trim($code))) {
            Cache::forget($cacheKey);
            return true;
        }

        return false;
    }
}
