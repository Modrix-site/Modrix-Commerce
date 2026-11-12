<?php

namespace ModrixCommerce\Sms\Providers;

use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsResult;

final class VonageProvider extends HttpSmsProvider
{
    public function code(): string
    {
        return 'vonage';
    }

    public function label(): string
    {
        return 'Vonage (Nexmo)';
    }

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'required' => true],
            ['key' => 'api_secret', 'label' => 'API secret', 'type' => 'secret', 'required' => true],
            ['key' => 'from', 'label' => 'From number or sender ID', 'required' => true],
        ];
    }

    public function send(SmsMessage $message, array $config): SmsResult
    {
        return $this->attempt(function () use ($message, $config) {
            $response = $this->http()->asForm()->post('https://rest.nexmo.com/sms/json', [
                'api_key' => $config['api_key'] ?? '',
                'api_secret' => $config['api_secret'] ?? '',
                'from' => $config['from'] ?? '',
                'to' => $message->digits(),
                'text' => $message->body,
                'type' => 'unicode',
            ]);

            if (! $response->successful()) {
                return SmsResult::fromResponse($response);
            }

            // HTTP 200 even on errors: status "0" means accepted.
            $first = $response->json('messages.0') ?? [];

            return ($first['status'] ?? null) === '0'
                ? SmsResult::sent($first['message-id'] ?? null)
                : SmsResult::failed('Vonage: '.($first['error-text'] ?? 'message rejected'));
        });
    }
}
