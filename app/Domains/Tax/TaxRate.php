<?php

namespace App\Domains\Tax;

final class TaxRate
{
    /**
     * @param  string  $percent  decimal string, e.g. "12.0000"
     */
    public function __construct(
        public readonly string $name,
        public readonly string $percent,
    ) {}
}
