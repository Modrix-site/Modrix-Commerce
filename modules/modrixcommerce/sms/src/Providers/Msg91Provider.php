<?php

namespace ModrixCommerce\Sms\Providers;

use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsResult;

/**
 * MSG91 sends through Flow templates (required in India by DLT rules), so the
 * message text is set in MSG91, not here. Each flow receives these variables:
 * `message` (the text rendered from this store's template), `code`,
 * `order_number`, `store`, `name`, `total`.
 */
final class Msg91Provider extends HttpSmsProvider
{
    public function code(): string
    {
        return 'msg91';
    }

    public function label(): string
    {
        return 'MSG91';
    }

    public function fields(): array
    {
        return [
            ['key' => 'auth_key', 'label' => 'Auth key', 'type' => 'secret', 'required' => true],
            ['key' => 'otp_template_id', 'label' => 'Flow template ID for verification codes', 'required' => true, 'help' => 'Variables available: ##code##, ##store##, ##message##.'],
            ['key' => 'updates_template_id', 'label' => 'Flow template ID for order updates', 'help' => 'Variables: ##order_number##, ##name##, ##total##, ##store##, ##message##. Leave empty to send order updates through the verification template’s ##message##.'],
        ];
    }

    public function send(SmsMessage $message, array $config): SmsResult
    {
        $template = $message->purpose === SmsMessage::PURPOSE_ORDER_UPDATE && ($config['updates_template_id'] ?? '') !== ''
            ? $config['updates_template_id']
            : ($config['otp_template_id'] ?? '');

        if ($template === '') {
            return SmsResult::failed('Set the MSG91 flow template ID.');
        }

        return $this->attempt(function () use ($message, $config, $template) {
            $response = $this->http()->asJson()->withHeaders(['authkey' => $config['auth_key'] ?? ''])
                ->post('https://control.msg91.com/api/v5/flow', [
                    'template_id' => $template,
                    'short_url' => '0',
                    'recipients' => [['mobiles' => $message->digits(), 'message' => $message->body, ...$message->variables]],
                ]);

            return $response->successful() && $response->json('type') === 'success'
                ? SmsResult::sent($response->json('message'))
                : SmsResult::fromResponse($response, 'MSG91: '.($response->json('message') ?? 'request rejected'));
        });
    }
}
