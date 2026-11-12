<?php

namespace ModrixCommerce\Sms\Providers;

use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsResult;

final class TwilioProvider extends HttpSmsProvider
{
    public function code(): string
    {
        return 'twilio';
    }

    public function label(): string
    {
        return 'Twilio';
    }

    public function fields(): array
    {
        return [
            ['key' => 'account_sid', 'label' => 'Account SID', 'required' => true, 'help' => 'Starts with AC.'],
            ['key' => 'auth_token', 'label' => 'Auth token', 'type' => 'secret', 'required' => true],
            ['key' => 'from', 'label' => 'From number or sender ID', 'help' => 'A Twilio number in E.164 (+15551234567) or an alphanumeric sender ID. Leave empty when using a Messaging Service.'],
            ['key' => 'messaging_service_sid', 'label' => 'Messaging Service SID', 'help' => 'Optional. Starts with MG; used instead of the From number.'],
        ];
    }

    public function send(SmsMessage $message, array $config): SmsResult
    {
        $sid = $config['account_sid'] ?? '';

        if (! preg_match('/^AC[a-f0-9]{32}$/i', $sid)) {
            return SmsResult::failed('The Twilio Account SID must start with AC followed by 32 characters.');
        }

        $payload = ['To' => $message->to, 'Body' => $message->body];

        if (($config['messaging_service_sid'] ?? '') !== '') {
            $payload['MessagingServiceSid'] = $config['messaging_service_sid'];
        } elseif (($config['from'] ?? '') !== '') {
            $payload['From'] = $config['from'];
        } else {
            return SmsResult::failed('Set a From number or a Messaging Service SID for Twilio.');
        }

        return $this->attempt(function () use ($sid, $config, $payload) {
            $response = $this->http()->asForm()->withBasicAuth($sid, $config['auth_token'] ?? '')
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload);

            return $response->successful()
                ? SmsResult::sent($response->json('sid'))
                : SmsResult::fromResponse($response, $response->json('message'));
        });
    }
}
