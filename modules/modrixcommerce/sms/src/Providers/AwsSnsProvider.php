<?php

namespace ModrixCommerce\Sms\Providers;

use Illuminate\Support\Carbon;
use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsResult;

/**
 * Amazon SNS direct-to-phone SMS (Publish with PhoneNumber), signed with
 * Signature Version 4 so no AWS SDK is needed. The IAM user only needs
 * sns:Publish.
 */
final class AwsSnsProvider extends HttpSmsProvider
{
    public function code(): string
    {
        return 'aws_sns';
    }

    public function label(): string
    {
        return 'Amazon SNS';
    }

    public function fields(): array
    {
        return [
            ['key' => 'access_key_id', 'label' => 'Access key ID', 'required' => true],
            ['key' => 'secret_access_key', 'label' => 'Secret access key', 'type' => 'secret', 'required' => true],
            ['key' => 'region', 'label' => 'Region', 'required' => true, 'default' => 'us-east-1', 'help' => 'An SNS region that supports SMS, e.g. us-east-1, ap-south-1, ap-southeast-1.'],
            ['key' => 'sender_id', 'label' => 'Sender ID', 'help' => 'Optional, where the destination country supports it.'],
        ];
    }

    public function send(SmsMessage $message, array $config): SmsResult
    {
        $region = $config['region'] ?? '';

        if (! preg_match('/^[a-z]{2}(-gov)?-[a-z]+-\d$/', $region)) {
            return SmsResult::failed('Enter a valid AWS region such as us-east-1.');
        }

        $params = [
            'Action' => 'Publish',
            'Version' => '2010-03-31',
            'PhoneNumber' => $message->to,
            'Message' => $message->body,
            'MessageAttributes.entry.1.Name' => 'AWS.SNS.SMS.SMSType',
            'MessageAttributes.entry.1.Value.DataType' => 'String',
            'MessageAttributes.entry.1.Value.StringValue' => 'Transactional',
        ];

        if (($config['sender_id'] ?? '') !== '') {
            $params += [
                'MessageAttributes.entry.2.Name' => 'AWS.SNS.SMS.SenderID',
                'MessageAttributes.entry.2.Value.DataType' => 'String',
                'MessageAttributes.entry.2.Value.StringValue' => $config['sender_id'],
            ];
        }

        ksort($params);
        $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $host = "sns.{$region}.amazonaws.com";
        $headers = $this->sign($host, $region, $body, $config['access_key_id'] ?? '', $config['secret_access_key'] ?? '', Carbon::now('UTC'));

        return $this->attempt(function () use ($host, $body, $headers) {
            $response = $this->http()->withHeaders($headers)->withBody($body, 'application/x-www-form-urlencoded; charset=utf-8')->post("https://{$host}/");

            if ($response->successful() && preg_match('#<MessageId>([^<]+)</MessageId>#', $response->body(), $m)) {
                return SmsResult::sent($m[1]);
            }

            preg_match('#<Message>([^<]+)</Message>#', $response->body(), $error);

            return SmsResult::fromResponse($response, isset($error[1]) ? 'Amazon SNS: '.$error[1] : null);
        });
    }

    /**
     * @return array<string, string>
     */
    public function sign(string $host, string $region, string $body, string $keyId, string $secret, Carbon $now): array
    {
        $amzDate = $now->format('Ymd\THis\Z');
        $date = $now->format('Ymd');
        $contentType = 'application/x-www-form-urlencoded; charset=utf-8';
        $signedHeaders = 'content-type;host;x-amz-date';
        $canonical = implode("\n", ['POST', '/', '', "content-type:{$contentType}", "host:{$host}", "x-amz-date:{$amzDate}", '', $signedHeaders, hash('sha256', $body)]);
        $scope = "{$date}/{$region}/sns/aws4_request";
        $toSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonical)]);

        $key = hash_hmac('sha256', $date, 'AWS4'.$secret, true);
        $key = hash_hmac('sha256', $region, $key, true);
        $key = hash_hmac('sha256', 'sns', $key, true);
        $key = hash_hmac('sha256', 'aws4_request', $key, true);
        $signature = hash_hmac('sha256', $toSign, $key);

        return [
            'X-Amz-Date' => $amzDate,
            'Authorization' => "AWS4-HMAC-SHA256 Credential={$keyId}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}",
        ];
    }
}
