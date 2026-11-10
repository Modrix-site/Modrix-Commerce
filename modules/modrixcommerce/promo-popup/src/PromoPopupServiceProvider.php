<?php

namespace EcoModular\PromoPopup;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Domains\Customers\PersonalDataExport;
use App\Models\User;
use EcoModular\PromoPopup\Models\PopupLead;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Places the marketing pop-up on storefront pages through the global
 * "storefront.overlay" slot. Everything is configured from module.json; the
 * provider only decides whether to show it and builds its props.
 */
class PromoPopupServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/promo-popup';

    private const MODES = ['email', 'coupon', 'both'];

    private const FREQUENCIES = ['session', 'once', 'always'];

    public function boot(StorefrontSlots $slots, ModuleSettingsSchema $settings, PersonalDataExport $export): void
    {
        $export->contribute('promo_popup', fn (User $user) => PopupLead::query()
            ->where('email', Str::lower($user->email))->get()
            ->map(fn (PopupLead $lead) => [
                'email' => $lead->email,
                'coupon_code' => $lead->coupon_code,
                'consented_at' => $lead->consented_at->toIso8601String(),
            ])->all());

        $slots->register('storefront.overlay', self::MODULE, 'PromoPopup', fn () => $this->props($settings));
    }

    /**
     * @return array<string, mixed>|null null hides the pop-up on this page
     */
    private function props(ModuleSettingsSchema $settings): ?array
    {
        if (! (bool) $settings->of(self::MODULE, 'enabled', true)) {
            return null;
        }

        $request = request();

        if ($request->routeIs('checkout.*') || $request->routeIs('account.*')) {
            return null;
        }

        $path = trim((string) $request->path(), '/');

        if ((string) $settings->of(self::MODULE, 'show_on', 'all') === 'home' && $path !== '') {
            return null;
        }

        $mode = (string) $settings->of(self::MODULE, 'mode', 'both');
        $frequency = (string) $settings->of(self::MODULE, 'frequency', 'session');
        $coupon = trim((string) $settings->of(self::MODULE, 'coupon_code', ''));

        return [
            'headline' => (string) $settings->of(self::MODULE, 'headline', ''),
            'body' => (string) $settings->of(self::MODULE, 'body', ''),
            'ctaLabel' => (string) $settings->of(self::MODULE, 'cta_label', 'Get my offer'),
            'mode' => in_array($mode, self::MODES, true) ? $mode : 'both',
            'couponCode' => $coupon !== '' ? $coupon : null,
            'couponNote' => (string) $settings->of(self::MODULE, 'coupon_note', ''),
            'consentText' => (string) $settings->of(self::MODULE, 'consent_text', ''),
            'delaySeconds' => max(0, min(120, (int) $settings->of(self::MODULE, 'delay_seconds', 6))),
            'frequency' => in_array($frequency, self::FREQUENCIES, true) ? $frequency : 'session',
            'page' => $path === '' ? '/' : '/'.$path,
            'submitUrl' => route('modrixcommerce.promo-popup.lead'),
        ];
    }
}
