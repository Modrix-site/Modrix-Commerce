<?php

namespace App\Domains\Payments\Gateway;

use Carbon\CarbonInterface;

/**
 * A hosted payment page created at the provider.
 */
final class PaymentSession
{
    public function __construct(
        public readonly string $reference,
        public readonly string $redirectUrl,
        public readonly ?CarbonInterface $expiresAt,
    ) {}
}
