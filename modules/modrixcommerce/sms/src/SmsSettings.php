<?php

namespace ModrixCommerce\Sms;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use ModrixCommerce\Sms\Contracts\SmsProvider;

/**
 * Store-level SMS settings. Provider credentials marked "secret" are kept
 * encrypted (SecretSettings) and never sent back to the browser.
 */
final class SmsSettings
{
    /** Order events that can be texted, with their default templates. */
    public const EVENTS = [
        'order_placed' => '{store}: thanks {name}! We received order {order_number} ({total}).',
        'order_paid' => '{store}: payment for order {order_number} ({total}) is confirmed.',
        'order_shipped' => '{store}: order {order_number} has shipped.{tracking}',
        'order_delivered' => '{store}: order {order_number} was delivered. Enjoy!',
        'order_cancelled' => '{store}: order {order_number} was cancelled.',
        'order_refunded' => '{store}: a refund for order {order_number} has been issued.',
    ];

    public const VERIFICATION_TEMPLATE = '{store}: your verification code is {code}. It expires in {minutes} minutes. Never share it.';

    public const DEFAULTS = [
        'provider' => 'log',
        'default_country_code' => '1',
        'require_at_registration' => true,
        'code_minutes' => 10,
        'verification_template' => self::VERIFICATION_TEMPLATE,
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
        private readonly SmsProviders $providers,
    ) {}

    public function value(string $key): mixed
    {
        return $this->settings->get('sms.'.$key, self::DEFAULTS[$key] ?? null);
    }

    public function provider(): ?SmsProvider
    {
        return $this->providers->find((string) $this->value('provider'));
    }

    /**
     * The provider's field values, secrets decrypted. Server-side only.
     *
     * @return array<string, string>
     */
    public function config(SmsProvider $provider): array
    {
        $config = [];

        foreach ($provider->fields() as $field) {
            $key = "{$provider->code()}.{$field['key']}";
            $config[$field['key']] = ($field['type'] ?? 'string') === 'secret'
                ? (string) $this->secrets->get('sms.'.$key)
                : (string) $this->settings->get('sms.provider.'.$key, $field['default'] ?? '');
        }

        return $config;
    }

    /**
     * What is missing before texts can be sent; empty when ready.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $provider = $this->provider();

        if ($provider === null) {
            return ['Choose an SMS provider.'];
        }

        if ($provider->code() === 'log' && app()->isProduction()) {
            return ['The "Log only" provider does not send texts. Choose a real SMS provider.'];
        }

        $config = $this->config($provider);
        $problems = [];

        foreach ($provider->fields() as $field) {
            if (($field['required'] ?? false) && ($config[$field['key']] ?? '') === '') {
                $problems[] = "{$provider->label()}: {$field['label']} is required.";
            }
        }

        return $problems;
    }

    public function ready(): bool
    {
        return $this->problems() === [];
    }

    /** Whether new customers must give and confirm a mobile number. */
    public function requiredAtRegistration(): bool
    {
        return (bool) $this->value('require_at_registration') && $this->ready();
    }

    public function eventEnabled(string $event): bool
    {
        return (bool) $this->settings->get("sms.events.{$event}.enabled", in_array($event, ['order_placed', 'order_shipped', 'order_delivered', 'order_cancelled'], true));
    }

    public function eventTemplate(string $event): string
    {
        $template = $this->settings->get("sms.events.{$event}.template");

        return is_string($template) && $template !== '' ? $template : self::EVENTS[$event];
    }

    /**
     * @param  array<string, mixed>  $data  validated admin input
     */
    public function update(array $data): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $data)) {
                $this->settings->set('sms.'.$key, $data[$key]);
            }
        }

        foreach (self::EVENTS as $event => $default) {
            if (isset($data['events'][$event])) {
                $this->settings->set("sms.events.{$event}.enabled", (bool) $data['events'][$event]['enabled']);
                $this->settings->set("sms.events.{$event}.template", (string) $data['events'][$event]['template']);
            }
        }

        $provider = $this->providers->find((string) ($data['provider'] ?? ''));

        if ($provider === null) {
            return;
        }

        foreach ($provider->fields() as $field) {
            $value = $data['config'][$field['key']] ?? null;
            $key = "{$provider->code()}.{$field['key']}";

            if (($field['type'] ?? 'string') === 'secret') {
                // Empty keeps the saved secret.
                if (is_string($value) && $value !== '') {
                    $this->secrets->put('sms.'.$key, $value);
                }
            } elseif ($value !== null) {
                $this->settings->set('sms.provider.'.$key, (string) $value);
            }
        }
    }

    /**
     * Everything the admin page needs. Secrets appear only as a saved/not saved
     * flag with their last four characters.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $providers = [];

        foreach ($this->providers->all() as $provider) {
            $values = [];
            $config = $this->config($provider);

            foreach ($provider->fields() as $field) {
                $values[$field['key']] = ($field['type'] ?? 'string') === 'secret'
                    ? ''
                    : $config[$field['key']];
            }

            $providers[] = [
                'code' => $provider->code(),
                'label' => $provider->label(),
                'fields' => array_map(fn (array $field) => $field + [
                    'type' => 'string',
                    'saved_hint' => ($field['type'] ?? '') === 'secret' ? $this->secrets->hint("sms.{$provider->code()}.{$field['key']}") : null,
                ], $provider->fields()),
                'values' => $values,
            ];
        }

        $events = [];
        foreach (array_keys(self::EVENTS) as $event) {
            $events[$event] = ['enabled' => $this->eventEnabled($event), 'template' => $this->eventTemplate($event)];
        }

        return [
            'provider' => (string) $this->value('provider'),
            'default_country_code' => (string) $this->value('default_country_code'),
            'require_at_registration' => (bool) $this->value('require_at_registration'),
            'code_minutes' => (int) $this->value('code_minutes'),
            'verification_template' => (string) $this->value('verification_template'),
            'events' => $events,
            'providers' => $providers,
            'problems' => $this->problems(),
        ];
    }
}
