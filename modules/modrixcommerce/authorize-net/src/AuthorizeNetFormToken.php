<?php

namespace EcoModular\AuthorizeNet;

use Illuminate\Support\Facades\Cache;

/**
 * The Accept Hosted form token is too long for the payment reference column.
 * It lives in the cache for the 15 minutes Authorize.Net keeps it valid, and
 * only the customer's handoff page reads it.
 */
final class AuthorizeNetFormToken
{
    public static function put(int $paymentId, string $token): void
    {
        Cache::put(self::key($paymentId), $token, now()->addMinutes(AuthorizeNetGateway::TOKEN_MINUTES));
    }

    public static function get(int $paymentId): ?string
    {
        $token = Cache::get(self::key($paymentId));

        return is_string($token) && AuthorizeNetEndpoint::isToken($token) ? $token : null;
    }

    private static function key(int $paymentId): string
    {
        return 'authorizenet:form:'.$paymentId;
    }
}
