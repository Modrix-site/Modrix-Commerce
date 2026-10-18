<?php

namespace EcoModular\PromoPopup\Http;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Http\Controllers\Controller;
use EcoModular\PromoPopup\Models\PopupLead;
use EcoModular\PromoPopup\PromoPopupServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Storefront: record a pop-up lead with its consent. Returns to the same page;
 * the flash message reveals the coupon. The response never says whether the
 * address was already on the list.
 */
class PopupLeadController extends Controller
{
    public function store(Request $request, ModuleSettingsSchema $settings): RedirectResponse
    {
        $mode = (string) $settings->of(PromoPopupServiceProvider::MODULE, 'mode', 'both');
        $collectEmail = in_array($mode, ['email', 'both'], true);

        $data = $request->validate([
            'email' => [$collectEmail ? 'required' : 'nullable', 'email', 'max:191'],
            'consent' => [$collectEmail ? 'accepted' : 'nullable'],
            'page' => ['nullable', 'string', 'max:191'],
        ], ['consent.accepted' => 'Tick the box to agree to receive emails.']);

        if (! $collectEmail) {
            // Coupon-only pop-ups store nothing personal.
            return back();
        }

        $email = Str::lower(trim((string) $data['email']));
        $coupon = trim((string) $settings->of(PromoPopupServiceProvider::MODULE, 'coupon_code', ''));
        $lead = PopupLead::query()->where('email', $email)->first() ?? new PopupLead;

        $lead->forceFill([
            'email' => $email,
            'coupon_code' => $coupon !== '' ? $coupon : null,
            'page' => $this->page($request),
            'consent_text' => (string) $settings->of(PromoPopupServiceProvider::MODULE, 'consent_text', ''),
            'consented_at' => now(),
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $coupon !== '' ? "Here is your code: {$coupon}" : 'Thank you. You are on the list.',
        ]);

        return back();
    }

    /** The storefront path the pop-up was answered on, without the query string. */
    private function page(Request $request): string
    {
        $page = $request->input('page');

        if (! is_string($page) || $page === '') {
            return '/';
        }

        return mb_substr('/'.ltrim(explode('?', $page)[0], '/'), 0, 191);
    }
}
