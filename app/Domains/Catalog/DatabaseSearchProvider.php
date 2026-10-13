<?php

namespace App\Domains\Catalog;

use App\Domains\Catalog\Contracts\SearchProvider;
use App\Domains\Catalog\Models\ProductTag;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;

/**
 * Default search: escaped LIKE matching on name, short description, SKU and
 * the translated name in the shopper's language.
 */
final class DatabaseSearchProvider implements SearchProvider
{
    public function apply(Builder $products, string $term): Builder
    {
        return $products->where(function ($q) use ($term) {
            Like::where($q, 'products.name', $term);
            Like::orWhere($q, 'products.short_description', $term);
            $q->orWhereHas('variants', fn ($v) => Like::where($v, 'sku', $term));
            // Tags match whole and literally (stored normalised).
            $q->orWhereHas('tags', fn ($t) => $t->where('tag', ProductTag::normalise($term)));
            // Names in the shopper's language.
            $q->orWhereHas('translations', fn ($t) => Like::where($t->where('locale', app()->getLocale()), 'name', $term));
        });
    }
}
