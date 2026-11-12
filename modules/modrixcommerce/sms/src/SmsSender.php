<?php

namespace ModrixCommerce\Sms;

use Illuminate\Support\Facades\Log;
use ModrixCommerce\Sms\Models\SmsMessageLog;
use Throwable;

/**
 * Sends a text through the configured provider and records the outcome in
 * sms_messages. Never throws for delivery problems: the result says what
 * happened.
 */
final class SmsSender
{
    public function __construct(private readonly SmsSettings $settings) {}

    /**
     * @param  array{user_id?: int|null, order_id?: int|null, log_body?: string}  $context
     *                                                                                      `log_body` replaces the body in the log (used to redact codes)
     * @param  SmsMessageLog|null  $log  an existing pending row to complete (idempotent order updates)
     */
    public function send(SmsMessage $message, array $context = [], ?SmsMessageLog $log = null): SmsResult
    {
        $provider = $this->settings->provider();
        $problems = $this->settings->problems();

        $log ??= SmsMessageLog::query()->create([
            'user_id' => $context['user_id'] ?? null,
            'order_id' => $context['order_id'] ?? null,
            'event' => $message->event,
            'purpose' => $message->purpose,
            'to' => $message->to,
            'body' => mb_substr($context['log_body'] ?? $message->body, 0, 1000),
            'provider' => $provider?->code() ?? 'none',
            'status' => 'pending',
        ]);

        if ($provider === null || $problems !== []) {
            $result = SmsResult::failed($problems[0] ?? 'No SMS provider is configured.');
        } else {
            try {
                $result = $provider->send($message, $this->settings->config($provider));
            } catch (Throwable $e) {
                Log::error('SMS provider failed', ['provider' => $provider->code(), 'exception' => $e]);
                $result = SmsResult::failed('The SMS provider failed unexpectedly. See the application log.');
            }
        }

        $log->forceFill([
            'provider' => $provider?->code() ?? 'none',
            'status' => $result->sent ? 'sent' : 'failed',
            'provider_message_id' => $result->messageId,
            'error' => $result->error,
        ])->save();

        return $result;
    }

    /**
     * Fills {placeholders} with plain values. Unknown placeholders are left as
     * typed; nothing is evaluated.
     *
     * @param  array<string, string>  $values
     */
    public static function render(string $template, array $values): string
    {
        $pairs = [];
        foreach ($values as $key => $value) {
            $pairs['{'.$key.'}'] = $value;
        }

        return trim(strtr($template, $pairs));
    }
}
