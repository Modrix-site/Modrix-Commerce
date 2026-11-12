<?php

namespace ModrixCommerce\Sms\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use ModrixCommerce\Sms\Models\SmsPhone;
use ModrixCommerce\Sms\PhoneNumber;
use ModrixCommerce\Sms\PhoneVerification;
use ModrixCommerce\Sms\SmsSettings;

/**
 * The customer's side: the code page after registration, and the account page
 * to change the number or turn order texts on and off.
 */
class PhoneController extends Controller
{
    public function __construct(private readonly PhoneVerification $verification, private readonly SmsSettings $settings) {}

    public function verify(Request $request): Response|RedirectResponse
    {
        $phone = $this->phone($request);

        if ($phone === null || ($phone->verified_at !== null && $phone->pending_phone === null)) {
            return redirect()->route('modrixcommerce.sms.account');
        }

        return Inertia::render('module/modrixcommerce/sms/storefront/verify', [
            'phone' => $phone->numberToVerify(),
            'changing' => $phone->verified_at !== null,
            'urls' => [
                'confirm' => route('modrixcommerce.sms.confirm'),
                'resend' => route('modrixcommerce.sms.resend'),
                'change' => route('modrixcommerce.sms.change'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $phone = $this->phone($request) ?? abort(404);
        $first = $phone->verified_at === null && $phone->pending_phone === null;
        $error = $this->verification->verify($this->user($request), $phone, $data['code']);

        if ($error !== null) {
            throw ValidationException::withMessages(['code' => $error]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your mobile number is confirmed.']);

        // Straight on to email verification (or the account) after sign-up.
        return $first ? redirect()->intended(route('account.show', absolute: false)) : redirect()->route('modrixcommerce.sms.account');
    }

    public function resend(Request $request): RedirectResponse
    {
        $phone = $this->phone($request) ?? abort(404);
        $result = $this->verification->send($this->user($request), $phone);

        if (! $result['sent']) {
            throw ValidationException::withMessages(['code' => $result['error']]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'We sent a new code.']);

        return back();
    }

    /** Sets a new number (during sign-up: corrects a typo) and texts a code to it. */
    public function change(Request $request): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $number = PhoneNumber::normalize($data['phone'], (string) $this->settings->value('default_country_code'))
            ?? throw ValidationException::withMessages(['phone' => 'Enter a valid mobile number, including the country code.']);

        if (! $this->settings->ready()) {
            throw ValidationException::withMessages(['phone' => 'Text messages are not available right now.']);
        }

        $user = $this->user($request);

        if (SmsPhone::query()->where('phone', $number)->whereNotNull('verified_at')->where('user_id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages(['phone' => 'This mobile number is already used by another account.']);
        }

        $phone = $this->phone($request);

        if ($phone !== null && $phone->verified_at !== null && $phone->phone === $number) {
            $phone->forceFill(['pending_phone' => null])->save();

            return redirect()->route('modrixcommerce.sms.account');
        }

        if ($phone === null) {
            // A first number added from the account page: pending_phone is set,
            // so RequireVerifiedPhone does not hold the account meanwhile.
            $phone = SmsPhone::query()->create(['user_id' => $user->id, 'phone' => $number, 'pending_phone' => $number, 'order_updates' => true]);
        } elseif ($phone->verified_at === null && $phone->pending_phone === null) {
            // Still in sign-up: correcting the number typed at registration.
            $phone->forceFill(['phone' => $number])->save();
        } else {
            $phone->forceFill(['pending_phone' => $number])->save();
        }

        $result = $this->verification->send($user, $phone);

        if (! $result['sent']) {
            throw ValidationException::withMessages(['phone' => $result['error']]);
        }

        return redirect()->route('modrixcommerce.sms.verify');
    }

    public function account(Request $request): Response
    {
        $phone = $this->phone($request);

        $verified = $phone !== null && $phone->verified_at !== null;

        return Inertia::render('module/modrixcommerce/sms/storefront/account', [
            'phone' => $verified ? $phone->phone : null,
            'pending' => $phone === null ? null : ($phone->pending_phone ?? ($verified ? null : $phone->phone)),
            'orderUpdates' => $phone !== null && $phone->order_updates,
            'available' => $this->settings->ready(),
            'urls' => [
                'change' => route('modrixcommerce.sms.change'),
                'preferences' => route('modrixcommerce.sms.preferences'),
                'verify' => route('modrixcommerce.sms.verify'),
            ],
        ]);
    }

    public function preferences(Request $request): RedirectResponse
    {
        $data = $request->validate(['order_updates' => ['required', 'boolean']]);
        $phone = $this->phone($request);

        if ($phone === null || $phone->verified_at === null) {
            throw ValidationException::withMessages(['order_updates' => 'Confirm a mobile number first.']);
        }

        $phone->forceFill(['order_updates' => $data['order_updates']])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => $data['order_updates'] ? 'Order updates by text are on.' : 'Order updates by text are off.']);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function phone(Request $request): ?SmsPhone
    {
        return SmsPhone::query()->where('user_id', $this->user($request)->id)->first();
    }
}
