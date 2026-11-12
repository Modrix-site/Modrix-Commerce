<?php

namespace App\Domains\Catalog\Enums;

/**
 * Built-in product types. A simple product has exactly one variant; a
 * variable product has one variant per option combination. Further types
 * (digital, bundle, …) are planned as extension-provided types.
 */
enum ProductType: string
{
    case Simple = 'simple';
    case Variable = 'variable';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Simple product',
            self::Variable => 'Product with variants',
        };
    }
}
