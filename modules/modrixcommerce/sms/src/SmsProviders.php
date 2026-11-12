<?php

namespace ModrixCommerce\Sms;

use InvalidArgumentException;
use ModrixCommerce\Sms\Contracts\SmsProvider;
use ModrixCommerce\Sms\Providers\AwsSnsProvider;
use ModrixCommerce\Sms\Providers\CustomHttpProvider;
use ModrixCommerce\Sms\Providers\Fast2SmsProvider;
use ModrixCommerce\Sms\Providers\LogProvider;
use ModrixCommerce\Sms\Providers\Msg91Provider;
use ModrixCommerce\Sms\Providers\PlivoProvider;
use ModrixCommerce\Sms\Providers\SemaphoreProvider;
use ModrixCommerce\Sms\Providers\TwilioProvider;
use ModrixCommerce\Sms\Providers\VonageProvider;

/**
 * The SMS gateways the store can choose from. Other modules add their own
 * with register() from their service provider's boot().
 */
final class SmsProviders
{
    /** @var array<string, SmsProvider> */
    private array $providers = [];

    public function __construct()
    {
        foreach ([
            new TwilioProvider, new VonageProvider, new Msg91Provider, new Fast2SmsProvider,
            new SemaphoreProvider, new AwsSnsProvider, new PlivoProvider, new CustomHttpProvider, new LogProvider,
        ] as $provider) {
            $this->register($provider);
        }
    }

    public function register(SmsProvider $provider): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{1,39}$/', $provider->code())) {
            throw new InvalidArgumentException("Invalid SMS provider code [{$provider->code()}].");
        }

        if (isset($this->providers[$provider->code()])) {
            throw new InvalidArgumentException("SMS provider [{$provider->code()}] is already registered.");
        }

        foreach ($provider->fields() as $field) {
            if (! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $field['key'])) {
                throw new InvalidArgumentException("SMS provider [{$provider->code()}] has an invalid field key [{$field['key']}].");
            }
        }

        $this->providers[$provider->code()] = $provider;
    }

    public function find(string $code): ?SmsProvider
    {
        return $this->providers[$code] ?? null;
    }

    /** @return array<string, SmsProvider> */
    public function all(): array
    {
        return $this->providers;
    }
}
