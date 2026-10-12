<?php

namespace App\Domains\Marketplace;

use RuntimeException;

/**
 * A licensing request was refused. `code` is stable and machine-readable
 * (invalid_key, license_inactive, activation_limit, not_activated,
 * not_entitled, release_not_found, download_expired, product_unavailable);
 * the message is safe to show to merchants.
 */
final class LicenseApiException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
