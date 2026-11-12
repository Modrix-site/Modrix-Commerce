<?php

namespace EcoModular\CloudConnector;

use RuntimeException;

/**
 * A marketplace call failed. `unavailable` is true when the service could
 * not be reached (network, timeout, 5xx); false when it answered with a
 * refusal (`errorCode` from the server, e.g. license_inactive).
 */
final class CloudError extends RuntimeException
{
    public function __construct(string $message, public readonly bool $unavailable = false, public readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }
}
