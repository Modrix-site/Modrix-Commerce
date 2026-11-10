<?php

namespace EcoModular\GdprCookieConsent;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use EcoModular\GdprCookieConsent\Support\CountryDetector;
use Illuminate\Support\ServiceProvider;

/**
 * Places the GDPR cookie-consent banner on storefront pages through the
 * "storefront.overlay" slot. Whether the banner applies is decided here from
 * the visitor's country; the React component plays no part in that decision so
 * the opt-in/opt-out choice is honoured server-side too.
 *
 * Consent records are linked to a random browser token (a cookie), not to a
 * user account, so no PersonalDataExport contribution is registered. Records
 * can still be reviewed, exported and erased from the admin page.
 */
class GdprCookieConsentServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/gdpr-cookie-consent';

    /**
     * EU/EEA member states plus the UK and Switzerland. GDPR applies to the
     * EU/EEA; the UK and CH have equivalent domestic laws.
     */
    private const GDPR_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
        'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
        'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'GB', 'CH',
    ];

    public function boot(StorefrontSlots $slots, ModuleSettingsSchema $settings): void
    {
        $slots->register('storefront.overlay', self::MODULE, 'GdprConsent', fn () => $this->props($settings));
    }

    /**
     * @return array<string, mixed>|null null hides the banner on this page/visitor
     */
    private function props(ModuleSettingsSchema $settings): ?array
    {
        if (! (bool) $settings->of(self::MODULE, 'enabled', true)) {
            return null;
        }

        $request = request();
        $audience = (string) $settings->of(self::MODULE, 'audience', 'gdpr');
        $country = CountryDetector::detect($request);

        if ($audience !== 'all') {
            if ($country === null || ! in_array($country, self::GDPR_COUNTRIES, true)) {
                return null;
            }
        }

        $path = trim((string) $request->path(), '/');
        $expiry = max(1, min(730, (int) $settings->of(self::MODULE, 'consent_expiry_days', 365)));

        $privacyUrl = trim((string) $settings->of(self::MODULE, 'privacy_url', ''));
        if ($privacyUrl !== '' && ! str_starts_with($privacyUrl, '/') && ! preg_match('#^https?://#i', $privacyUrl)) {
            $privacyUrl = '/'.$privacyUrl;
        }

        return [
            'title' => (string) $settings->of(self::MODULE, 'title', 'We value your privacy'),
            'body' => (string) $settings->of(self::MODULE, 'body', ''),
            'privacyUrl' => $privacyUrl !== '' ? $privacyUrl : null,
            'acceptLabel' => (string) $settings->of(self::MODULE, 'accept_label', 'Accept all'),
            'rejectLabel' => (string) $settings->of(self::MODULE, 'reject_label', 'Reject'),
            'customizeLabel' => (string) $settings->of(self::MODULE, 'customize_label', 'Customize'),
            'allowCustomize' => (bool) $settings->of(self::MODULE, 'allow_customize', true),
            'essentialText' => (string) $settings->of(self::MODULE, 'essential_text', ''),
            'analyticsText' => (string) $settings->of(self::MODULE, 'analytics_text', ''),
            'marketingText' => (string) $settings->of(self::MODULE, 'marketing_text', ''),
            'expiryDays' => $expiry,
            'page' => $path === '' ? '/' : '/'.$path,
            'storeUrl' => route('modrixcommerce.gdpr-cookie-consent.store'),
        ];
    }
}
