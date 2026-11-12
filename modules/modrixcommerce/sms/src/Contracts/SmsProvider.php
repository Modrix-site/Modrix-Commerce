<?php

namespace ModrixCommerce\Sms\Contracts;

use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsResult;

/**
 * An SMS gateway. Built-in providers live in ModrixCommerce\Sms\Providers;
 * another module can add its own with SmsProviders::register().
 *
 * send() must not throw for gateway errors: return SmsResult::failed() with a
 * message safe to show to staff (never echo credentials back).
 */
interface SmsProvider
{
    /** Stable lowercase code, e.g. "twilio". Stored in settings. */
    public function code(): string;

    public function label(): string;

    /**
     * Credential and option fields shown on the admin page.
     *
     * @return list<array{key: string, label: string, type?: 'string'|'secret'|'select'|'text', required?: bool, help?: string, options?: list<array{value: string, label: string}>, default?: string}>
     */
    public function fields(): array;

    /**
     * @param  array<string, string>  $config  values of fields(); secrets decrypted
     */
    public function send(SmsMessage $message, array $config): SmsResult;
}
