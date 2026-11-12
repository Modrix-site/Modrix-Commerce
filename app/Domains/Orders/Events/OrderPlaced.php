<?php

namespace App\Domains\Orders\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after the surrounding transaction commits. Listeners must be
 * idempotent: queued delivery may repeat.
 */
final class OrderPlaced
{
    use Dispatchable;

    public function __construct(public readonly int $orderId) {}
}
