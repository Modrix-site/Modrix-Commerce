<?php

namespace ModrixCommerce\Sms;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

final readonly class SmsResult
{
    private function __construct(public bool $sent, public ?string $messageId, public ?string $error) {}

    public static function sent(?string $messageId = null): self
    {
        return new self(true, $messageId === null ? null : Str::limit($messageId, 120, ''), null);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, Str::limit($error, 480));
    }

    /** A failure built from an HTTP response, keeping the gateway's own error text. */
    public static function fromResponse(Response $response, ?string $detail = null): self
    {
        $detail ??= Str::limit(trim(strip_tags($response->body())), 300);

        return self::failed("HTTP {$response->status()}".($detail !== '' ? ": {$detail}" : ''));
    }
}
