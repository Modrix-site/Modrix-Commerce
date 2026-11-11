<?php

namespace Database\Seeders;

use App\Models\User;
use EcoModular\LoginMonitoring\Models\LoginAllowedIp;
use EcoModular\LoginMonitoring\Models\LoginAuditLog;
use EcoModular\LoginMonitoring\Models\LoginBlockedIp;
use EcoModular\LoginMonitoring\Models\LoginEvent;
use EcoModular\LoginMonitoring\Models\LoginMfaConfiguration;
use EcoModular\LoginMonitoring\Models\LoginSecurityIncident;
use EcoModular\LoginMonitoring\Models\LoginUserSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LoginMonitoringDemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::limit(5)->get();
        if ($users->isEmpty()) {
            return;
        }

        $admin = $users->first();

        // 1. Seed Allowed IPs
        LoginAllowedIp::firstOrCreate(['ip_address' => '127.0.0.1'], [
            'label' => 'Localhost Development',
            'description' => 'Development loopback interface',
            'created_by' => $admin->name,
        ]);

        LoginAllowedIp::firstOrCreate(['ip_address' => '192.168.1.100'], [
            'label' => 'Corporate Head Office',
            'description' => 'HQ Secure static gateway',
            'created_by' => $admin->name,
        ]);

        // 2. Seed Blocked IPs
        LoginBlockedIp::firstOrCreate(['ip_address' => '198.51.100.22'], [
            'reason' => 'High frequency distributed brute force attack',
            'attack_type' => 'brute_force',
            'auto_blocked' => true,
            'hit_count' => 42,
            'expires_at' => now()->addDays(7),
            'created_by' => 'Automated Defense Engine',
        ]);

        LoginBlockedIp::firstOrCreate(['ip_address' => '203.0.113.88'], [
            'reason' => 'Tor Exit Node credential spraying',
            'attack_type' => 'credential_stuffing',
            'auto_blocked' => true,
            'hit_count' => 18,
            'expires_at' => now()->addDays(3),
            'created_by' => 'Automated Defense Engine',
        ]);

        // 3. Seed MFA configurations
        foreach ($users as $idx => $user) {
            LoginMfaConfiguration::firstOrCreate(['user_id' => $user->id], [
                'mfa_enabled' => $idx % 2 === 0,
                'mfa_type' => $idx === 0 ? 'authenticator' : 'email_otp',
                'phone_number' => '+1 (555) 234-567'.$idx,
                'last_used_at' => now()->subHours($idx * 5 + 1),
            ]);
        }

        // 4. Seed Active Sessions
        foreach ($users as $idx => $user) {
            LoginUserSession::firstOrCreate(['session_id' => 'sess_'.$user->id.'_active'], [
                'user_id' => $user->id,
                'ip_address' => '192.168.1.'.(10 + $idx),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
                'device_type' => $idx % 2 === 0 ? 'desktop' : 'mobile',
                'browser' => $idx % 2 === 0 ? 'Chrome' : 'Safari',
                'operating_system' => $idx % 2 === 0 ? 'Windows' : 'iOS',
                'country_code' => 'US',
                'city' => 'San Francisco',
                'is_current' => $idx === 0,
                'is_revoked' => false,
                'last_activity' => now()->subMinutes($idx * 15 + 2),
                'expires_at' => now()->addHours(24),
            ]);
        }

        // 5. Seed Historical & Recent Login Events (Past 7 days)
        $devices = [
            ['browser' => 'Chrome', 'os' => 'Windows', 'type' => 'desktop', 'city' => 'New York', 'country' => 'US', 'lat' => 40.71, 'lon' => -74.00],
            ['browser' => 'Safari', 'os' => 'macOS', 'type' => 'desktop', 'city' => 'Los Angeles', 'country' => 'US', 'lat' => 34.05, 'lon' => -118.24],
            ['browser' => 'Firefox', 'os' => 'Linux', 'type' => 'desktop', 'city' => 'London', 'country' => 'GB', 'lat' => 51.50, 'lon' => -0.12],
            ['browser' => 'Edge', 'os' => 'Windows', 'type' => 'desktop', 'city' => 'Berlin', 'country' => 'DE', 'lat' => 52.52, 'lon' => 13.40],
            ['browser' => 'Mobile Safari', 'os' => 'iOS', 'type' => 'mobile', 'city' => 'Tokyo', 'country' => 'JP', 'lat' => 35.68, 'lon' => 139.69],
        ];

        for ($day = 6; $day >= 0; $day--) {
            $baseTime = Carbon::now()->subDays($day);
            $countForDay = rand(6, 12);

            for ($i = 0; $i < $countForDay; $i++) {
                $dev = $devices[array_rand($devices)];
                $targetUser = $users->random();
                $isFailed = rand(1, 10) <= 2;
                $isSuspicious = rand(1, 10) <= 2;
                $riskScore = $isSuspicious ? rand(55, 95) : ($isFailed ? rand(25, 45) : rand(5, 20));

                $reasons = [];
                if ($isSuspicious) {
                    $reasons[] = ['indicator' => 'Unfamiliar device fingerprint signature', 'points' => 25];
                    if ($riskScore > 70) {
                        $reasons[] = ['indicator' => 'Multiple accounts accessed from origin IP', 'points' => 35];
                    }
                }

                LoginEvent::create([
                    'event_uuid' => (string) Str::uuid(),
                    'user_id' => $targetUser->id,
                    'email' => $targetUser->email,
                    'ip_address' => '203.0.113.'.rand(10, 240),
                    'status' => $isFailed ? 'failed' : 'success',
                    'failure_reason' => $isFailed ? 'Invalid credentials provided' : null,
                    'risk_score' => $riskScore,
                    'threat_level' => $riskScore >= 80 ? 'critical' : ($riskScore >= 50 ? 'high' : ($riskScore >= 25 ? 'medium' : 'low')),
                    'device_fingerprint' => hash('sha256', $dev['browser'].$dev['os'].rand(1, 5)),
                    'device_type' => $dev['type'],
                    'browser' => $dev['browser'],
                    'operating_system' => $dev['os'],
                    'user_agent' => "Mozilla/5.0 ({$dev['os']}) {$dev['browser']}/120.0",
                    'country_code' => $dev['country'],
                    'city' => $dev['city'],
                    'latitude' => $dev['lat'],
                    'longitude' => $dev['lon'],
                    'is_suspicious' => $isSuspicious,
                    'suspicious_reasons' => $reasons,
                    'session_id' => $isFailed ? null : (string) Str::uuid(),
                    'created_at' => $baseTime->copy()->subMinutes(rand(10, 700)),
                ]);
            }
        }

        // 6. Seed Security Incidents
        LoginSecurityIncident::create([
            'incident_uuid' => (string) Str::uuid(),
            'incident_type' => 'brute_force',
            'user_id' => $admin->id,
            'email' => $admin->email,
            'ip_address' => '198.51.100.22',
            'severity' => 'high',
            'status' => 'open',
            'description' => "Brute force suspected: 5 consecutive failed logins targeting {$admin->email}",
            'metadata' => ['attempt_count' => 5],
        ]);

        LoginSecurityIncident::create([
            'incident_uuid' => (string) Str::uuid(),
            'incident_type' => 'impossible_travel',
            'user_id' => $users->last()->id,
            'email' => $users->last()->email,
            'ip_address' => '203.0.113.88',
            'severity' => 'critical',
            'status' => 'investigating',
            'description' => 'Impossible travel: New York to London (5,570 km) within 15 minutes',
            'metadata' => ['distance_km' => 5570, 'speed_kmh' => 22280],
        ]);

        LoginSecurityIncident::create([
            'incident_uuid' => (string) Str::uuid(),
            'incident_type' => 'credential_stuffing',
            'user_id' => null,
            'email' => 'admin@store.com',
            'ip_address' => '198.51.100.99',
            'severity' => 'critical',
            'status' => 'resolved',
            'description' => 'Automated dictionary attack probing multiple administrator handles',
            'metadata' => ['probed_accounts' => 14],
            'resolved_by' => $admin->name,
            'resolved_at' => now()->subDay(),
            'resolution_notes' => 'IP permanently blacklisted and rate limiting adjusted.',
        ]);

        // 7. Seed Audit Logs
        LoginAuditLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'action' => 'Enabled Login Monitoring engine and defense middleware',
            'target_type' => 'module',
            'target_id' => 'modrixcommerce/login-monitoring',
            'details' => ['version' => '1.0.0'],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(2),
        ]);

        LoginAuditLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'action' => 'Added IP to blocklist: 198.51.100.22',
            'target_type' => 'blocked_ip',
            'target_id' => '198.51.100.22',
            'details' => ['reason' => 'Brute force pattern'],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subHours(12),
        ]);
    }
}
