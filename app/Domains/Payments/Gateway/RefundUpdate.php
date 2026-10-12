<?php

namespace App\Domains\Payments\Gateway;

use App\Domains\Pricing\Money;

/**
 * A provider-neutral fact about a refund.
 */
final class RefundUpdate
{
    public const SUCCEEDED = 'succeeded';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public function __construct(
        public readonly string $reference,
        /** The provider's charge/payment id the refund belongs to (`payments.provider_payment_id`). */
        public readonly ?string $paymentReference,
        public readonly Money $amount,
        /** One of the class constants. */
        public readonly string $status,
        public readonly ?string $failureReason = null,
    ) {}
}
