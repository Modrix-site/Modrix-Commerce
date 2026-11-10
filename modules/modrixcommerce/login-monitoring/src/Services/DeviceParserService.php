<?php

namespace EcoModular\LoginMonitoring\Services;

class DeviceParserService
{
    public function parse(?string $userAgent): array
    {
        $ua = $userAgent ?: 'Unknown';
        $uaLower = strtolower($ua);

        // Detect Browser
        $browser = 'Unknown';
        if (str_contains($uaLower, 'edg')) {
            $browser = 'Edge';
        } elseif (str_contains($uaLower, 'chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($uaLower, 'firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($uaLower, 'safari')) {
            $browser = 'Safari';
        } elseif (str_contains($uaLower, 'opera') || str_contains($uaLower, 'opr')) {
            $browser = 'Opera';
        }

        // Detect OS
        $os = 'Unknown';
        if (str_contains($uaLower, 'windows nt 10.0') || str_contains($uaLower, 'windows 11') || str_contains($uaLower, 'windows 10')) {
            $os = 'Windows 11/10';
        } elseif (str_contains($uaLower, 'windows')) {
            $os = 'Windows';
        } elseif (str_contains($uaLower, 'mac os x')) {
            $os = 'macOS';
        } elseif (str_contains($uaLower, 'android')) {
            $os = 'Android';
        } elseif (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad') || str_contains($uaLower, 'ios')) {
            $os = 'iOS';
        } elseif (str_contains($uaLower, 'linux')) {
            $os = 'Linux';
        }

        // Detect Device Type
        $deviceType = 'desktop';
        if (str_contains($uaLower, 'ipad') || str_contains($uaLower, 'tablet')) {
            $deviceType = 'tablet';
        } elseif (str_contains($uaLower, 'mobile') || str_contains($uaLower, 'iphone') || str_contains($uaLower, 'android')) {
            $deviceType = 'mobile';
        } elseif (str_contains($uaLower, 'bot') || str_contains($uaLower, 'crawler') || str_contains($uaLower, 'spider')) {
            $deviceType = 'bot';
        }

        // Generate persistent device fingerprint
        $fingerprint = hash('sha256', "{$browser}:{$os}:{$deviceType}:" . substr($ua, 0, 100));

        return [
            'browser' => $browser,
            'operating_system' => $os,
            'device_type' => $deviceType,
            'fingerprint' => $fingerprint,
        ];
    }
}
