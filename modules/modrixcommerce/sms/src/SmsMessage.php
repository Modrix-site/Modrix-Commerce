<?php

namespace ModrixCommerce\Sms;

/**
 * One outgoing text. `to` is E.164 (+15551234567). `variables` carries the raw
 * template values (code, order_number, …) for gateways that send through
 * pre-registered templates (MSG91 flows, Fast2SMS DLT) instead of free text.
 */
final readonly class SmsMessage
{
    public const PURPOSE_VERIFICATION = 'verification';

    public const PURPOSE_ORDER_UPDATE = 'order_update';

    public const PURPOSE_TEST = 'test';

    /**
     * @param  array<string, string>  $variables
     */
    public function __construct(
        public string $to,
        public string $body,
        public string $purpose,
        public array $variables = [],
        public ?string $event = null,
    ) {}

    /** The number without "+", as most gateways want it. */
    public function digits(): string
    {
        return ltrim($this->to, '+');
    }
}
