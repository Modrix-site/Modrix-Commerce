<?php

namespace App\Domains\Catalog;

use App\Domains\Catalog\Contracts\SearchProvider;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;

/**
 * Default search: escaped LIKE matching on name, short description and SKU.
 */
final class DatabaseSearchProvider implements SearchProvider
{
    public function apply(Builder $products, string $term): Builder
    {
        return $products->where(function ($q) use ($term) {
            Like::where($q, 'products.name', $term);
            Like::orWhere($q, 'products.short_description', $term);
            $q->orWhereHas('variants', fn ($v) => Like::where($v, 'sku', $term));
        });
    }
}
