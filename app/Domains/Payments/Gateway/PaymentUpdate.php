<?php

namespace App\Domains\Payments\Gateway;

use App\Domains\Pricing\Money;

/**
 * A provider-neutral fact about a payment session.
 */
final class PaymentUpdate
{
    public function __construct(
        public readonly PaymentUpdateKind $kind,
        /** The session reference (`payments.provider_reference`). */
        public readonly string $sessionReference,
        /** The provider's charge/payment id, once known. */
        public readonly ?string $paymentReference = null,
        /** The amount the provider says was paid; null when unknown. */
        public readonly ?Money $amount = null,
        public readonly ?string $failureReason = null,
    ) {}
}
