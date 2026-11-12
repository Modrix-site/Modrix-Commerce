<?php

namespace ModrixCommerce\Sms\Providers;

use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsResult;

final class PlivoProvider extends HttpSmsProvider
{
    public function code(): string
    {
        return 'plivo';
    }

    public function label(): string
    {
        return 'Plivo';
    }

    public function fields(): array
    {
        return [
            ['key' => 'auth_id', 'label' => 'Auth ID', 'required' => true],
            ['key' => 'auth_token', 'label' => 'Auth token', 'type' => 'secret', 'required' => true],
            ['key' => 'from', 'label' => 'From number or sender ID', 'required' => true],
        ];
    }

    public function send(SmsMessage $message, array $config): SmsResult
    {
        $id = $config['auth_id'] ?? '';

        if (! preg_match('/^[A-Za-z0-9]{10,40}$/', $id)) {
            return SmsResult::failed('The Plivo Auth ID looks wrong.');
        }

        return $this->attempt(function () use ($message, $config, $id) {
            $response = $this->http()->asJson()->withBasicAuth($id, $config['auth_token'] ?? '')
                ->post("https://api.plivo.com/v1/Account/{$id}/Message/", [
                    'src' => $config['from'] ?? '', 'dst' => $message->digits(), 'text' => $message->body,
                ]);

            return $response->successful()
                ? SmsResult::sent($response->json('message_uuid.0'))
                : SmsResult::fromResponse($response, $response->json('error'));
        });
    }
}
