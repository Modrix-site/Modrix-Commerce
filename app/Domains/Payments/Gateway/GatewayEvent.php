<?php

namespace App\Domains\Payments\Gateway;

/**
 * A verified webhook event, normalised. At most one of `payment` and `refund`
 * is set; neither means the event is irrelevant and is ignored.
 */
final class GatewayEvent
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?PaymentUpdate $payment = null,
        public readonly ?RefundUpdate $refund = null,
    ) {}

    public function isRelevant(): bool
    {
        return $this->payment !== null || $this->refund !== null;
    }
}
