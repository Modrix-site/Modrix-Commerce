<?php

namespace App\Domains\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Stock of a tracked variant fell to or below its low-stock threshold.
 * Dispatched after the surrounding transaction commits.
 */
final class InventoryLow
{
    use Dispatchable;

    public function __construct(
        public readonly int $variantId,
        public readonly string $sku,
        public readonly int $onHand,
        public readonly int $threshold,
    ) {}
}
