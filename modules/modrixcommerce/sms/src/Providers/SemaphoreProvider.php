<?php

namespace ModrixCommerce\Sms\Providers;

use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsResult;

/** Semaphore (Philippines). */
final class SemaphoreProvider extends HttpSmsProvider
{
    public function code(): string
    {
        return 'semaphore';
    }

    public function label(): string
    {
        return 'Semaphore (Philippines)';
    }

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'type' => 'secret', 'required' => true],
            ['key' => 'sender_name', 'label' => 'Sender name', 'help' => 'Must be approved in your Semaphore account. Leave empty for the default "SEMAPHORE".'],
        ];
    }

    public function send(SmsMessage $message, array $config): SmsResult
    {
        if (! str_starts_with($message->to, '+63')) {
            return SmsResult::failed('Semaphore only delivers to Philippine mobile numbers (+63).');
        }

        return $this->attempt(function () use ($message, $config) {
            $payload = ['apikey' => $config['api_key'] ?? '', 'number' => '0'.substr($message->digits(), 2), 'message' => $message->body];

            if (($config['sender_name'] ?? '') !== '') {
                $payload['sendername'] = $config['sender_name'];
            }

            $response = $this->http()->asForm()->post('https://api.semaphore.co/api/v4/messages', $payload);
            $first = $response->json('0');

            return $response->successful() && is_array($first) && isset($first['message_id'])
                ? SmsResult::sent((string) $first['message_id'])
                : SmsResult::fromResponse($response);
        });
    }
}
