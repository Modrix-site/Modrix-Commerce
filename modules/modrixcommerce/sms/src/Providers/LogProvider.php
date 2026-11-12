<?php

namespace ModrixCommerce\Sms\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ModrixCommerce\Sms\Contracts\SmsProvider;
use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsResult;

/**
 * Writes messages to the application log instead of sending them. For local
 * development and staging only: customers never receive their codes.
 */
final class LogProvider implements SmsProvider
{
    public function code(): string
    {
        return 'log';
    }

    public function label(): string
    {
        return 'Log only (testing, nothing is sent)';
    }

    public function fields(): array
    {
        return [];
    }

    public function send(SmsMessage $message, array $config): SmsResult
    {
        Log::info('SMS (log provider, not sent)', ['to' => $message->to, 'body' => $message->body]);

        return SmsResult::sent('log-'.Str::lower(Str::random(12)));
    }
}
