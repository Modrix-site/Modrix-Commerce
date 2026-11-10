<?php

namespace EcoModular\GoogleAnalytics;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use Illuminate\Support\ServiceProvider;

/**
 * Loads the Google tag on the storefront. The tag is injected into the page
 * head from the global "storefront.overlay" slot, so it runs in every theme;
 * page views are recorded in the browser on first load and on each Inertia
 * navigation. The manifests's content_security adds Google's hosts to the CSP.
 */
class GoogleAnalyticsServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/google-analytics';

    public function boot(StorefrontSlots $slots, ModuleSettingsSchema $settings): void
    {
        $slots->register('storefront.overlay', self::MODULE, 'GoogleAnalytics', fn () => $this->props($settings));
    }

    /**
     * @return array<string, mixed>|null null when analytics is off or unconfigured
     */
    private function props(ModuleSettingsSchema $settings): ?array
    {
        if (! (bool) $settings->of(self::MODULE, 'enabled', true)) {
            return null;
        }

        $measurementId = strtoupper(trim((string) $settings->of(self::MODULE, 'measurement_id', '')));

        // A GA4 stream id (G-XXXXXXXXXX) or a legacy Universal Analytics id (UA-...).
        if (! preg_match('/^(?:G|UA)-[A-Z0-9-]{4,}$/', $measurementId)) {
            return null;
        }

        return [
            'measurementId' => $measurementId,
            'trackPageViews' => (bool) $settings->of(self::MODULE, 'track_page_views', true),
            'anonymizeIp' => (bool) $settings->of(self::MODULE, 'anonymize_ip', true),
            'debugMode' => (bool) $settings->of(self::MODULE, 'debug_mode', false),
        ];
    }
}
