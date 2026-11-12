<?php

namespace ModrixCommerce\Sms\Registration;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use ModrixCommerce\Sms\Models\SmsPhone;
use ModrixCommerce\Sms\PhoneVerification;

/**
 * Texts the first code once the account exists (after the registration
 * transaction committed). If sending fails the customer can resend from the
 * verification page.
 */
final class SendCodeAfterRegistration
{
    public function __construct(private readonly PhoneVerification $verification) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $phone = SmsPhone::query()->where('user_id', $event->user->id)->whereNull('verified_at')->whereNull('pending_phone')->first();

        if ($phone !== null) {
            $this->verification->send($event->user, $phone);
        }
    }
}
