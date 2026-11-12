<?php

namespace App\Domains\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates URL slugs that are unique within a table.
 */
final class Slugger
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $source, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($source), 180, '') ?: 'item';
        $slug = $base;
        $suffix = 2;

        while (self::exists($model, $slug, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private static function exists(string $model, string $slug, ?int $ignoreId): bool
    {
        $query = $model::query()->where('slug', $slug);

        if (method_exists($model, 'bootSoftDeletes')) {
            $query->withoutGlobalScopes();
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
