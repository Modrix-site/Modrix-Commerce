<?php

namespace ModrixCommerce\Sms\Providers;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use ModrixCommerce\Sms\Contracts\SmsProvider;
use ModrixCommerce\Sms\SmsResult;

abstract class HttpSmsProvider implements SmsProvider
{
    protected function http(): PendingRequest
    {
        return Http::timeout(15)->connectTimeout(5)->acceptJson();
    }

    /**
     * @param  Closure(): SmsResult  $send
     */
    protected function attempt(Closure $send): SmsResult
    {
        try {
            return $send();
        } catch (ConnectionException) {
            return SmsResult::failed('Could not reach '.$this->label().'. Check the server’s internet connection and try again.');
        }
    }
}
