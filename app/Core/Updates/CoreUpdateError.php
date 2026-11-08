<?php

namespace App\Core\Updates;

use RuntimeException;

final class CoreUpdateError extends RuntimeException
{
    public function __construct(string $message, public readonly bool $notConnected = false, public readonly bool $unavailable = false)
    {
        parent::__construct($message);
    }
}
