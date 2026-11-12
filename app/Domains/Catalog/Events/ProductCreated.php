<?php

namespace App\Domains\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after the creating transaction commits.
 */
final class ProductCreated
{
    use Dispatchable;

    public function __construct(public readonly int $productId) {}
}
