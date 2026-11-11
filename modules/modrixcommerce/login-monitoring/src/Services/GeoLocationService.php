<?php

namespace EcoModular\LoginMonitoring\Services;

use Illuminate\Http\Request;

class GeoLocationService
{
    // Common capital / major city coordinate estimates for country code fallback
    private const COUNTRY_COORDINATES = [
        'US' => ['lat' => 38.8951, 'lon' => -77.0364, 'city' => 'Washington'],
        'GB' => ['lat' => 51.5074, 'lon' => -0.1278, 'city' => 'London'],
        'DE' => ['lat' => 52.5200, 'lon' => 13.4050, 'city' => 'Berlin'],
        'FR' => ['lat' => 48.8566, 'lon' => 2.3522, 'city' => 'Paris'],
        'SG' => ['lat' => 1.3521, 'lon' => 103.8198, 'city' => 'Singapore'],
        'JP' => ['lat' => 35.6762, 'lon' => 139.6503, 'city' => 'Tokyo'],
        'AU' => ['lat' => -33.8688, 'lon' => 151.2093, 'city' => 'Sydney'],
        'CA' => ['lat' => 45.4215, 'lon' => -75.6972, 'city' => 'Ottawa'],
        'AE' => ['lat' => 25.2048, 'lon' => 55.2708, 'city' => 'Dubai'],
        'IN' => ['lat' => 28.6139, 'lon' => 77.2090, 'city' => 'New Delhi'],
        'BR' => ['lat' => -15.7975, 'lon' => -47.8919, 'city' => 'Brasília'],
        'PH' => ['lat' => 14.5995, 'lon' => 120.9842, 'city' => 'Manila'],
        'NL' => ['lat' => 52.3676, 'lon' => 4.9041, 'city' => 'Amsterdam'],
    ];

    public function resolveLocation(Request $request): array
    {
        $country = null;
        $headers = [
            'cf-ipcountry',
            'x-country-code',
            'cloudfront-viewer-country',
            'x-geo-country',
        ];

        foreach ($headers as $header) {
            $val = $request->header($header);
            if (! empty($val) && strlen($val) === 2 && ctype_alpha($val)) {
                $country = strtoupper($val);
                break;
            }
        }

        $city = $request->header('cf-ipcity') ?: ($request->header('x-geo-city') ?: null);
        $lat = null;
        $lon = null;

        if ($request->hasHeader('cf-iplatitude') && $request->hasHeader('cf-iplongitude')) {
            $lat = (float) $request->header('cf-iplatitude');
            $lon = (float) $request->header('cf-iplongitude');
        } elseif ($country && isset(self::COUNTRY_COORDINATES[$country])) {
            $coords = self::COUNTRY_COORDINATES[$country];
            $lat = $coords['lat'];
            $lon = $coords['lon'];
            if (! $city) {
                $city = $coords['city'];
            }
        }

        return [
            'country_code' => $country,
            'city' => $city,
            'latitude' => $lat,
            'longitude' => $lon,
        ];
    }

    public function calculateDistanceKm(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }

        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 1);
    }
}
