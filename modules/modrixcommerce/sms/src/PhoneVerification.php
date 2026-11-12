<?php

namespace ModrixCommerce\Sms;

use App\Core\Settings\StoreSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use ModrixCommerce\Sms\Models\SmsPhone;

/**
 * One-time codes that prove a customer owns a mobile number.
 *
 * - 6 digits, valid for a configurable number of minutes, single use.
 * - 5 wrong guesses burn the code.
 * - Sending is limited per account (1 a minute, 5 an hour) and per number
 *   (5 an hour) so the form cannot be used to run up SMS bills.
 * - Codes are stored as an HMAC keyed with the app key, never in clear text,
 *   and the message log keeps a redacted copy.
 */
final class PhoneVerification
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly SmsSettings $settings,
        private readonly SmsSender $sender,
        private readonly StoreSettings $store,
    ) {}

    /**
     * Sends a fresh code to the number awaiting verification.
     *
     * @return array{sent: bool, error: string|null, retry_after: int|null}
     */
    public function send(User $user, SmsPhone $phone): array
    {
        $number = $phone->numberToVerify();

        foreach ([
            ["sms-otp:user-minute:{$user->id}", 1, 60],
            ["sms-otp:user-hour:{$user->id}", 5, 3600],
            ['sms-otp:phone-hour:'.sha1($number), 5, 3600],
        ] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);

                return ['sent' => false, 'error' => "Please wait {$seconds} seconds before asking for another code.", 'retry_after' => $seconds];
            }
        }

        foreach ([["sms-otp:user-minute:{$user->id}", 60], ["sms-otp:user-hour:{$user->id}", 3600], ['sms-otp:phone-hour:'.sha1($number), 3600]] as [$key, $decay]) {
            RateLimiter::hit($key, $decay);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = max(1, min(60, (int) $this->settings->value('code_minutes')));

        DB::table('sms_verification_codes')->where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        DB::table('sms_verification_codes')->insert([
            'user_id' => $user->id,
            'phone' => $number,
            'code_hash' => self::hash($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($minutes),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $values = ['store' => $this->store->name(), 'code' => $code, 'minutes' => (string) $minutes, 'name' => (string) $user->name];
        $template = (string) $this->settings->value('verification_template');
        $body = SmsSender::render(str_contains($template, '{code}') ? $template : SmsSettings::VERIFICATION_TEMPLATE, $values);

        $result = $this->sender->send(
            new SmsMessage($number, $body, SmsMessage::PURPOSE_VERIFICATION, ['code' => $code, 'store' => $values['store']]),
            ['user_id' => $user->id, 'log_body' => str_replace($code, '••••••', $body)],
        );

        return [
            'sent' => $result->sent,
            'error' => $result->sent ? null : 'We could not send the text message. Check the number or try again in a minute.',
            'retry_after' => 60,
        ];
    }

    /**
     * @return string|null an error message, or null when the number is now verified
     */
    public function verify(User $user, SmsPhone $phone, string $code): ?string
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        return DB::transaction(function () use ($user, $phone, $code) {
            $row = DB::table('sms_verification_codes')
                ->where('user_id', $user->id)
                ->where('phone', $phone->numberToVerify())
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($row === null || now()->greaterThan($row->expires_at) || $row->attempts >= self::MAX_ATTEMPTS) {
                return 'This code has expired. Ask for a new one.';
            }

            $attempts = $row->attempts + 1;
            $matches = strlen($code) === 6 && hash_equals($row->code_hash, self::hash($code));

            DB::table('sms_verification_codes')->where('id', $row->id)->update([
                'attempts' => $attempts,
                'consumed_at' => $matches || $attempts >= self::MAX_ATTEMPTS ? now() : null,
                'updated_at' => now(),
            ]);

            if (! $matches) {
                return $attempts >= self::MAX_ATTEMPTS
                    ? 'Too many wrong codes. Ask for a new one.'
                    : 'That code is not right. Check the text message and try again.';
            }

            $number = $phone->numberToVerify();

            if (SmsPhone::query()->where('phone', $number)->whereNotNull('verified_at')->where('user_id', '!=', $user->id)->exists()) {
                return 'This mobile number is already used by another account.';
            }

            $phone->forceFill(['phone' => $number, 'pending_phone' => null, 'verified_at' => now()])->save();

            return null;
        });
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
