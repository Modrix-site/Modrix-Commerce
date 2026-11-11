<?php

namespace EcoModular\LoginMonitoring\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Models\User;
use EcoModular\LoginMonitoring\Models\LoginBlockedIp;
use EcoModular\LoginMonitoring\Models\LoginEvent;
use EcoModular\LoginMonitoring\Models\LoginSecurityIncident;
use EcoModular\LoginMonitoring\Models\LoginTrustedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SuspiciousLoginService
{
    public const MODULE = 'modrixcommerce/login-monitoring';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
        private readonly GeoLocationService $geo,
    ) {}

    public function evaluateLogin(Request $request, string $email, ?User $user, array $deviceInfo, array $location): array
    {
        $enabled = (bool) $this->settings->of(self::MODULE, 'enabled', true);
        if (! $enabled) {
            return [
                'risk_score' => 0,
                'threat_level' => 'low',
                'is_suspicious' => false,
                'reasons' => [],
            ];
        }

        $ip = $request->ip() ?? '127.0.0.1';
        $score = 0;
        $reasons = [];

        // 1. IP Blacklist check
        $isBlocked = LoginBlockedIp::query()
            ->where('ip_address', $ip)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($isBlocked) {
            $score += 100;
            $reasons[] = ['indicator' => 'IP is listed on Login Security Blacklist', 'points' => 100];
        }

        // 2. Unfamiliar / New Device Check
        if ($user) {
            $isTrusted = LoginTrustedDevice::query()
                ->where('user_id', $user->id)
                ->where('device_fingerprint', $deviceInfo['fingerprint'])
                ->where('is_revoked', false)
                ->exists();

            if (! $isTrusted) {
                // If user has previous logins, flag new device
                $hasPriorLogins = LoginEvent::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'success')
                    ->exists();

                if ($hasPriorLogins) {
                    $score += 25;
                    $reasons[] = ['indicator' => "New unfamiliar device ({$deviceInfo['browser']} on {$deviceInfo['operating_system']})", 'points' => 25];
                }
            }
        }

        // 3. Impossible Travel Scenario
        if ($user && $location['latitude'] !== null && $location['longitude'] !== null) {
            $lastLogin = LoginEvent::query()
                ->where('user_id', $user->id)
                ->where('status', 'success')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByDesc('created_at')
                ->first();

            if ($lastLogin && $lastLogin->created_at) {
                $elapsedHours = max(0.01, $lastLogin->created_at->diffInMinutes(now()) / 60);
                $distanceKm = $this->geo->calculateDistanceKm(
                    $lastLogin->latitude,
                    $lastLogin->longitude,
                    $location['latitude'],
                    $location['longitude']
                );

                if ($distanceKm !== null && $distanceKm > 100) {
                    $impliedSpeedKmh = $distanceKm / $elapsedHours;
                    $threshold = (int) $this->settings->of(self::MODULE, 'impossible_travel_speed_kmh', 800);

                    if ($impliedSpeedKmh > $threshold) {
                        $score += 45;
                        $reasons[] = [
                            'indicator' => "Impossible travel detected: {$distanceKm} km in ".round($elapsedHours, 1).'h ('.round($impliedSpeedKmh).' km/h)',
                            'points' => 45,
                        ];

                        $this->createIncident(
                            'impossible_travel',
                            $user,
                            $email,
                            $ip,
                            'high',
                            "Impossible travel detected for {$email} ({$distanceKm} km in ".round($elapsedHours, 1).'h)',
                            ['distance_km' => $distanceKm, 'speed_kmh' => $impliedSpeedKmh, 'from' => $lastLogin->city, 'to' => $location['city']]
                        );
                    }
                }
            }
        }

        // 4. Credential Stuffing / Multiple Accounts Access from Same IP
        $keyIpAccounts = "login_ip_accounts_{$ip}";
        $accounts = Cache::get($keyIpAccounts, []);
        if (! in_array($email, $accounts, true)) {
            $accounts[] = $email;
            Cache::put($keyIpAccounts, $accounts, 900); // 15 mins
        }

        if (count($accounts) >= 3) {
            $score += 35;
            $reasons[] = [
                'indicator' => 'Multiple accounts ('.count($accounts).') accessed from this IP in past 15 mins',
                'points' => 35,
            ];

            $this->createIncident(
                'credential_stuffing',
                $user,
                $email,
                $ip,
                'critical',
                "Credential stuffing suspected from IP {$ip} probing ".count($accounts).' accounts',
                ['accounts_probed' => $accounts]
            );
        }

        // 5. VPN / Proxy Header Detection
        if ($request->hasHeader('via') || $request->hasHeader('x-forwarded-for-original') || $request->hasHeader('cf-connecting-ip-proxy')) {
            $score += 15;
            $reasons[] = ['indicator' => 'Traffic routed through proxy or VPN header signature', 'points' => 15];
        }

        $finalScore = min(100, $score);
        $threatLevel = match (true) {
            $finalScore >= 80 => 'critical',
            $finalScore >= 50 => 'high',
            $finalScore >= 25 => 'medium',
            default => 'low',
        };

        return [
            'risk_score' => $finalScore,
            'threat_level' => $threatLevel,
            'is_suspicious' => $finalScore >= 30,
            'reasons' => $reasons,
        ];
    }

    public function createIncident(
        string $type,
        ?User $user,
        string $email,
        string $ip,
        string $severity,
        string $description,
        ?array $metadata = null
    ): LoginSecurityIncident {
        return LoginSecurityIncident::create([
            'incident_uuid' => (string) Str::uuid(),
            'incident_type' => $type,
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $ip,
            'severity' => $severity,
            'status' => 'open',
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
