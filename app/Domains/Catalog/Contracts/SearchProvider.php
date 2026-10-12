<?php

namespace App\Domains\Catalog\Contracts;

use App\Domains\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Storefront product search (stable public API).
 *
 * Receives the query of products already visible to the shopper and narrows
 * it to the matches for the search term. Implementations must only add
 * constraints: removing visibility constraints would expose drafts.
 * Bind a module's implementation to replace the default LIKE search.
 */
interface SearchProvider
{
    /**
     * @param  Builder<Product>  $products
     * @return Builder<Product>
     */
    public function apply(Builder $products, string $term): Builder;
}
