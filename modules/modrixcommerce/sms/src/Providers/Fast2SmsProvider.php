<?php

namespace ModrixCommerce\Sms\Providers;

use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsResult;

/**
 * Fast2SMS (India). "Quick SMS" sends the store's own text; the DLT route sends
 * a registered DLT template with the values separated by "|" in this order:
 * verification → code; order updates → order number.
 */
final class Fast2SmsProvider extends HttpSmsProvider
{
    public function code(): string
    {
        return 'fast2sms';
    }

    public function label(): string
    {
        return 'Fast2SMS';
    }

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'type' => 'secret', 'required' => true],
            ['key' => 'route', 'label' => 'Route', 'type' => 'select', 'default' => 'q', 'options' => [
                ['value' => 'q', 'label' => 'Quick SMS (free text)'],
                ['value' => 'dlt', 'label' => 'DLT (registered templates)'],
            ]],
            ['key' => 'sender_id', 'label' => 'DLT sender ID', 'help' => 'Needed for the DLT route.'],
            ['key' => 'otp_message_id', 'label' => 'DLT message ID for verification codes'],
            ['key' => 'updates_message_id', 'label' => 'DLT message ID for order updates'],
        ];
    }

    public function send(SmsMessage $message, array $config): SmsResult
    {
        $digits = $message->digits();

        if (! str_starts_with($digits, '91') || strlen($digits) !== 12) {
            return SmsResult::failed('Fast2SMS only delivers to Indian mobile numbers (+91).');
        }

        $payload = ['numbers' => substr($digits, 2), 'flash' => '0'];

        if (($config['route'] ?? 'q') === 'dlt') {
            $id = $message->purpose === SmsMessage::PURPOSE_ORDER_UPDATE ? ($config['updates_message_id'] ?? '') : ($config['otp_message_id'] ?? '');

            if ($id === '' || ($config['sender_id'] ?? '') === '') {
                return SmsResult::failed('Set the Fast2SMS DLT sender ID and message ID.');
            }

            $payload += [
                'route' => 'dlt', 'sender_id' => $config['sender_id'], 'message' => $id,
                'variables_values' => $message->purpose === SmsMessage::PURPOSE_ORDER_UPDATE
                    ? ($message->variables['order_number'] ?? '')
                    : ($message->variables['code'] ?? ''),
            ];
        } else {
            $payload += ['route' => 'q', 'message' => $message->body, 'language' => 'english'];
        }

        return $this->attempt(function () use ($payload, $config) {
            $response = $this->http()->asJson()->withHeaders(['authorization' => $config['api_key'] ?? ''])
                ->post('https://www.fast2sms.com/dev/bulkV2', $payload);

            return $response->successful() && $response->json('return') === true
                ? SmsResult::sent($response->json('request_id'))
                : SmsResult::fromResponse($response, 'Fast2SMS: '.json_encode($response->json('message') ?? 'request rejected'));
        });
    }
}
