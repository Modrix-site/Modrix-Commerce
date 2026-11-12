<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;

/**
 * Portable "contains" search for user-supplied terms.
 *
 * Wildcards in the term are escaped with "!" and the query states the escape
 * character explicitly, because the default differs between drivers
 * (MySQL uses backslash, SQLite has none).
 */
final class Like
{
    public static function pattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @param  literal-string  $column  a column name written in code (never user input)
     * @return TBuilder
     */
    public static function where(Builder $query, string $column, string $term, string $boolean = 'and'): Builder
    {
        // The column is interpolated into SQL, so only plain identifiers are accepted.
        if (! preg_match('/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/', $column)) {
            throw new InvalidArgumentException("Invalid column name [{$column}].");
        }

        $query->whereRaw(new Expression("{$column} LIKE ? ESCAPE '!'"), [self::pattern($term)], $boolean);

        return $query;
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @param  literal-string  $column
     * @return TBuilder
     */
    public static function orWhere(Builder $query, string $column, string $term): Builder
    {
        return self::where($query, $column, $term, 'or');
    }
}
