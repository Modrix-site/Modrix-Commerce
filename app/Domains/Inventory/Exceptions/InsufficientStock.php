<?php

namespace App\Domains\Inventory\Exceptions;

use RuntimeException;

final class InsufficientStock extends RuntimeException
{
    /**
     * @param  array<int, array{variant_id: int, sku: string, requested: int, available: int}>  $shortages
     */
    public function __construct(public readonly array $shortages)
    {
        parent::__construct('Insufficient stock for: '.implode(', ', array_column($shortages, 'sku')));
    }
}
