<?php

namespace ModrixCommerce\Sms\Registration;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use ModrixCommerce\Sms\Models\SmsPhone;
use ModrixCommerce\Sms\PhoneNumber;
use ModrixCommerce\Sms\SmsSettings;

/**
 * Wraps the platform's CreateNewUser: while an SMS provider is ready and the
 * store requires it, registration also needs a valid mobile number. The number
 * is saved unverified; SendCodeAfterRegistration texts the code and
 * RequireVerifiedPhone holds the account until it is entered.
 */
final class RequirePhoneOnRegistration implements CreatesNewUsers
{
    /** Sign-ups that trigger a text, per IP address per hour. */
    public const PER_IP_PER_HOUR = 10;

    public function __construct(private readonly CreatesNewUsers $inner, private readonly SmsSettings $settings) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        if (! $this->settings->requiredAtRegistration()) {
            return $this->inner->create($input);
        }

        $raw = $input['phone'] ?? null;
        $phone = is_string($raw) && mb_strlen($raw) <= 30
            ? PhoneNumber::normalize($raw, (string) $this->settings->value('default_country_code'))
            : null;

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => is_string($raw) && trim($raw) !== ''
                ? 'Enter a valid mobile number, including the country code if you are outside the store’s country.'
                : 'Enter your mobile number. We text a code to confirm it.']);
        }

        if (SmsPhone::query()->where('phone', $phone)->whereNotNull('verified_at')->exists()) {
            throw ValidationException::withMessages(['phone' => 'This mobile number is already used by another account.']);
        }

        $key = 'sms-register-ip:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::PER_IP_PER_HOUR)) {
            throw ValidationException::withMessages(['phone' => 'Too many sign-ups from your network. Please try again later.']);
        }

        return DB::transaction(function () use ($input, $phone, $key) {
            $user = $this->inner->create($input);
            RateLimiter::hit($key, 3600);

            SmsPhone::query()->create([
                'user_id' => $user->id,
                'phone' => $phone,
                'verified_at' => null,
                'order_updates' => filter_var($input['sms_updates'] ?? false, FILTER_VALIDATE_BOOL),
            ]);

            return $user;
        });
    }
}
