<?php

namespace App\Domains\Payments\Gateway;

use RuntimeException;

/**
 * A provider request failed. The message is safe to show to staff; it never
 * contains credentials. `retryable` is true for network errors, rate limits
 * and provider outages.
 */
final class GatewayException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
