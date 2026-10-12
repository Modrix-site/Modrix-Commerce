<?php

namespace App\Domains\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Who publishes marketplace products. Version 1 has exactly one, the
 * platform owner (`is_platform`); vendors are a later phase (spec 12.5).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_platform
 */
class Publisher extends Model
{
    protected $table = 'marketplace_publishers';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_platform' => 'boolean'];
    }

    public static function platform(): self
    {
        return self::query()->where('is_platform', true)->firstOrFail();
    }
}
