<?php

namespace App\Domains\Payments\Gateway;

use RuntimeException;

/**
 * A provider request failed. The message is safe to show to staff; it never
 * contains credentials. `retryable` is true for network errors, rate limits
 * and provider outages. `customerMessage`, when set, is shown to the shopper
 * instead of the generic error (e.g. a provider's approved decline wording).
 */
final class GatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?string $customerMessage = null,
    ) {
        parent::__construct($message);
    }
}
