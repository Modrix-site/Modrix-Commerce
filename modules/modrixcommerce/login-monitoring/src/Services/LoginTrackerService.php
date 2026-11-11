<?php

namespace EcoModular\LoginMonitoring\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Models\User;
use EcoModular\LoginMonitoring\Models\LoginEvent;
use EcoModular\LoginMonitoring\Models\LoginFailedAttempt;
use EcoModular\LoginMonitoring\Models\LoginTrustedDevice;
use EcoModular\LoginMonitoring\Models\LoginUserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoginTrackerService
{
    public const MODULE = 'modrixcommerce/login-monitoring';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
        private readonly DeviceParserService $deviceParser,
        private readonly GeoLocationService $geo,
        private readonly SuspiciousLoginService $suspiciousService,
        private readonly LoginNotificationService $notificationService,
    ) {}

    public function isLockedOut(string $email, string $ip): ?LoginFailedAttempt
    {
        $record = LoginFailedAttempt::query()
            ->where('email', $email)
            ->where('ip_address', $ip)
            ->first();

        if ($record && $record->isLockedOutActive()) {
            return $record;
        }

        return null;
    }

    public function recordSuccess(User $user, Request $request): LoginEvent
    {
        $ip = $request->ip() ?? '127.0.0.1';
        $email = $user->email;
        $device = $this->deviceParser->parse($request->userAgent());
        $location = $this->geo->resolveLocation($request);

        // Reset failed attempts on success
        LoginFailedAttempt::query()
            ->where('email', $email)
            ->where('ip_address', $ip)
            ->delete();

        // Evaluate risk
        $eval = $this->suspiciousService->evaluateLogin($request, $email, $user, $device, $location);

        $sessionId = $request->hasSession() ? $request->session()->getId() : (string) Str::uuid();

        // Create LoginEvent
        $event = LoginEvent::create([
            'event_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'email' => $email,
            'ip_address' => $ip,
            'status' => 'success',
            'risk_score' => $eval['risk_score'],
            'threat_level' => $eval['threat_level'],
            'device_fingerprint' => $device['fingerprint'],
            'device_type' => $device['device_type'],
            'browser' => $device['browser'],
            'operating_system' => $device['operating_system'],
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'country_code' => $location['country_code'],
            'city' => $location['city'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'is_suspicious' => $eval['is_suspicious'],
            'suspicious_reasons' => $eval['reasons'],
            'session_id' => $sessionId,
            'created_at' => now(),
        ]);

        // Upsert User Session
        $sessionLifetime = (int) $this->settings->of(self::MODULE, 'session_lifetime_hours', 24);
        LoginUserSession::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => $user->id,
                'ip_address' => $ip,
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'device_type' => $device['device_type'],
                'browser' => $device['browser'],
                'operating_system' => $device['operating_system'],
                'country_code' => $location['country_code'],
                'city' => $location['city'],
                'is_current' => true,
                'is_revoked' => false,
                'last_activity' => now(),
                'expires_at' => now()->addHours($sessionLifetime),
            ]
        );

        // Record or update Trusted Device
        LoginTrustedDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_fingerprint' => $device['fingerprint']],
            [
                'device_name' => "{$device['browser']} on {$device['operating_system']}",
                'browser' => $device['browser'],
                'operating_system' => $device['operating_system'],
                'ip_address' => $ip,
                'last_used_at' => now(),
                'is_revoked' => false,
            ]
        );

        // Dispatch notifications if suspicious or new device
        if ($eval['is_suspicious']) {
            $this->notificationService->notifySuspiciousLogin($event);
        }

        return $event;
    }

    public function recordFailed(string $email, Request $request, string $reason = 'Invalid credentials'): LoginEvent
    {
        $ip = $request->ip() ?? '127.0.0.1';
        $user = User::query()->where('email', $email)->first();
        $device = $this->deviceParser->parse($request->userAgent());
        $location = $this->geo->resolveLocation($request);

        // Track failed attempt count
        $failedRecord = LoginFailedAttempt::query()->firstOrNew([
            'email' => $email,
            'ip_address' => $ip,
        ]);

        $failedRecord->attempt_count = ($failedRecord->attempt_count ?? 0) + 1;
        $failedRecord->last_attempt_at = now();

        $maxAttempts = (int) $this->settings->of(self::MODULE, 'max_failed_attempts', 5);
        $lockoutMinutes = (int) $this->settings->of(self::MODULE, 'lockout_duration_minutes', 30);

        if ($failedRecord->attempt_count >= $maxAttempts) {
            $failedRecord->is_locked_out = true;
            $failedRecord->locked_until = now()->addMinutes($lockoutMinutes);
            $failedRecord->lockout_reason = "Exceeded {$maxAttempts} consecutive failed login attempts";

            // Record security incident
            $this->suspiciousService->createIncident(
                'brute_force',
                $user,
                $email,
                $ip,
                'high',
                "Brute force suspected: {$failedRecord->attempt_count} failed attempts for {$email} from {$ip}",
                ['attempt_count' => $failedRecord->attempt_count, 'locked_until' => $failedRecord->locked_until]
            );

            $this->notificationService->notifyBruteForceLockout($email, $ip, $failedRecord->attempt_count);
        }

        $failedRecord->save();

        // Calculate risk score for failed event
        $riskScore = min(100, 20 + ($failedRecord->attempt_count * 15));
        $threatLevel = $riskScore >= 80 ? 'critical' : ($riskScore >= 50 ? 'high' : 'medium');

        return LoginEvent::create([
            'event_uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $ip,
            'status' => 'failed',
            'failure_reason' => $reason,
            'risk_score' => $riskScore,
            'threat_level' => $threatLevel,
            'device_fingerprint' => $device['fingerprint'],
            'device_type' => $device['device_type'],
            'browser' => $device['browser'],
            'operating_system' => $device['operating_system'],
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'country_code' => $location['country_code'],
            'city' => $location['city'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'is_suspicious' => $failedRecord->attempt_count >= 3,
            'suspicious_reasons' => [
                ['indicator' => "Failed login: {$reason} (Attempt #{$failedRecord->attempt_count})", 'points' => $riskScore],
            ],
            'session_id' => null,
            'created_at' => now(),
        ]);
    }
}
