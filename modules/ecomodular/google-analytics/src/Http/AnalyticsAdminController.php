<?php

namespace EcoModular\GoogleAnalytics\Http;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Http\Controllers\Controller;
use EcoModular\GoogleAnalytics\GoogleAnalyticsServiceProvider as Module;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin: the Google Analytics status page (`can:analytics.manage`). The
 * measurement ID and the toggles are edited on the extension's settings page;
 * this page only reports whether the tag is live on the storefront.
 */
class AnalyticsAdminController extends Controller
{
    public function index(ModuleSettingsSchema $settings): Response
    {
        $measurementId = strtoupper(trim((string) $settings->of(Module::MODULE, 'measurement_id', '')));
        $valid = (bool) preg_match('/^(?:G|UA)-[A-Z0-9-]{4,}$/', $measurementId);
        $enabled = (bool) $settings->of(Module::MODULE, 'enabled', true);
        $extension = Extension::query()->where('name', Module::MODULE)->first();

        return Inertia::render('module/ecomodular/google-analytics/admin/index', [
            'state' => [
                'enabled' => $enabled,
                'measurementId' => $measurementId !== '' ? $measurementId : null,
                'measurementIdValid' => $valid,
                'live' => $enabled && $valid,
                'trackPageViews' => (bool) $settings->of(Module::MODULE, 'track_page_views', true),
                'anonymizeIp' => (bool) $settings->of(Module::MODULE, 'anonymize_ip', true),
                'debugMode' => (bool) $settings->of(Module::MODULE, 'debug_mode', false),
            ],
            'settingsUrl' => $extension !== null ? route('admin.extensions.show', $extension) : route('admin.extensions.index'),
        ]);
    }
}
