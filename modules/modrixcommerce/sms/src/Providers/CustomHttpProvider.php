<?php

namespace ModrixCommerce\Sms\Providers;

use Illuminate\Support\Arr;
use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsResult;

/**
 * Any HTTPS SMS gateway, described by a request template. Placeholders:
 * {to} (+15551234567), {to_digits} (15551234567), {message}, {code},
 * {order_number}, {sender}, {api_key}. Values are escaped for where they go
 * (URL-encoded in the URL, JSON-escaped in a JSON body, form-encoded in a
 * form body) and are never evaluated.
 */
final class CustomHttpProvider extends HttpSmsProvider
{
    public function code(): string
    {
        return 'custom_http';
    }

    public function label(): string
    {
        return 'Custom HTTP gateway';
    }

    public function fields(): array
    {
        return [
            ['key' => 'url', 'label' => 'Endpoint URL', 'required' => true, 'help' => 'HTTPS only. Placeholders allowed, e.g. https://sms.example.com/send?to={to_digits}'],
            ['key' => 'method', 'label' => 'Method', 'type' => 'select', 'default' => 'POST', 'options' => [
                ['value' => 'POST', 'label' => 'POST'], ['value' => 'GET', 'label' => 'GET'],
            ]],
            ['key' => 'format', 'label' => 'Body format', 'type' => 'select', 'default' => 'json', 'options' => [
                ['value' => 'json', 'label' => 'JSON'], ['value' => 'form', 'label' => 'Form (key=value lines)'], ['value' => 'none', 'label' => 'No body'],
            ]],
            ['key' => 'body', 'label' => 'Body template', 'type' => 'text', 'default' => '{"to": "{to}", "from": "{sender}", "text": "{message}"}', 'help' => 'JSON, or one key=value per line for form bodies.'],
            ['key' => 'headers', 'label' => 'Headers', 'type' => 'text', 'default' => 'Authorization: Bearer {api_key}', 'help' => 'One "Name: value" per line.'],
            ['key' => 'api_key', 'label' => 'API key ({api_key})', 'type' => 'secret'],
            ['key' => 'sender', 'label' => 'Sender ({sender})'],
            ['key' => 'success_contains', 'label' => 'Success only if the response contains', 'help' => 'Optional. Some gateways answer HTTP 200 for errors; e.g. "status":"ok".'],
            ['key' => 'message_id_path', 'label' => 'Message ID in the JSON response', 'help' => 'Optional dot path, e.g. data.id'],
        ];
    }

    public function send(SmsMessage $message, array $config): SmsResult
    {
        $values = [
            '{to}' => $message->to,
            '{to_digits}' => $message->digits(),
            '{message}' => $message->body,
            '{code}' => $message->variables['code'] ?? '',
            '{order_number}' => $message->variables['order_number'] ?? '',
            '{sender}' => $config['sender'] ?? '',
            '{api_key}' => $config['api_key'] ?? '',
        ];

        $url = strtr($config['url'] ?? '', array_map('rawurlencode', $values));

        if (($error = self::urlProblem($url)) !== null) {
            return SmsResult::failed($error);
        }

        $headers = [];
        foreach (preg_split('/\R/', $config['headers'] ?? '') ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $value = trim(strtr($value, $values));
            if (! preg_match('/^[A-Za-z0-9-]{1,64}$/', trim($name)) || preg_match('/[\r\n]/', $value)) {
                return SmsResult::failed('A custom gateway header is not in "Name: value" form.');
            }
            $headers[trim($name)] = $value;
        }

        $method = ($config['method'] ?? 'POST') === 'GET' ? 'GET' : 'POST';
        $format = $config['format'] ?? 'json';
        $request = $this->http()->withHeaders($headers);
        $fields = [];

        if ($method === 'POST' && $format === 'json') {
            $escaped = array_map(fn (string $v) => substr((string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1), $values);
            $body = strtr($config['body'] ?? '', $escaped);

            if (! is_array(json_decode($body, true))) {
                return SmsResult::failed('The custom gateway body template is not valid JSON.');
            }

            $request = $request->withBody($body, 'application/json');
        } elseif ($method === 'POST' && $format === 'form') {
            foreach (preg_split('/\R|&/', $config['body'] ?? '') ?: [] as $pair) {
                if (trim($pair) !== '') {
                    [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                    $fields[trim($key)] = strtr(trim($value), $values);
                }
            }
            $request = $request->asForm();
        }

        return $this->attempt(function () use ($request, $method, $url, $config, $fields) {
            $response = $method === 'GET' ? $request->get($url) : $request->post($url, $fields);
            $needle = $config['success_contains'] ?? '';

            if (! $response->successful() || ($needle !== '' && ! str_contains($response->body(), $needle))) {
                return SmsResult::fromResponse($response);
            }

            $path = $config['message_id_path'] ?? '';
            $id = $path !== '' ? Arr::get((array) $response->json(), $path) : null;

            return SmsResult::sent(is_scalar($id) ? (string) $id : null);
        });
    }

    /** Null when the URL may be called. */
    public static function urlProblem(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host'])) {
            return 'The custom gateway URL must be a valid https:// address.';
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')
            || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            return 'The custom gateway URL must point to a public internet host.';
        }

        return null;
    }
}
